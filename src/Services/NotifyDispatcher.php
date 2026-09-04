<?php

namespace Nawasara\Alerting\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Models\AlertState;
use Nawasara\Notification\Facades\Notify;
use Nawasara\Vault\Facades\Vault;

/**
 * Bridges the alerting state machine to nawasara/notification's channel
 * fan-out. Resolves audience, renders subject + body from the rule's
 * template/view, and calls Notify::to(...)->send() per channel configured
 * for the severity.
 *
 * Three kinds — 'fired', 'renotified', 'resolved' — drive subject prefix
 * and body view selection. Body view fallback chain:
 *   rule->bodyView() → alerting::emails.alert-{kind} → alerting::emails.alert-fired
 */
class NotifyDispatcher
{
    public function __construct(
        protected RecipientResolver $recipients,
    ) {}

    public function dispatch(AlertState $state, AlertRuleDefinition $rule, string $kind): void
    {
        if (! in_array($kind, ['fired', 'renotified', 'resolved'], true)) {
            throw new \InvalidArgumentException("Invalid dispatch kind: {$kind}");
        }

        // Audience = role-based groups + directly configured addresses. The
        // extras cover shared mailboxes / people without an account, and keep
        // alerts flowing when a role happens to have no members.
        $recipients = $this->recipients->resolveBySeverity($state->severity);
        $emails = collect($recipients->pluck('email')->filter()->all())
            ->merge($this->recipients->extraEmailsBySeverity($state->severity))
            ->unique()
            ->values()
            ->all();

        $channels = config("nawasara-alerting.severity.{$state->severity}.channels", ['email']);

        // ⚠️ Tidak boleh berhenti hanya karena daftar surelnya kosong.
        //
        // Dulu di sini ada `return` bila tidak ada penerima surel. Setelah
        // Telegram ada, itu berarti kanal yang sehat ikut dibungkam oleh
        // ketiadaan penerima kanal LAIN — dan justru pada deployment yang
        // memang memilih Telegram saja, peringatannya tidak akan pernah
        // sampai, tanpa satu pun galat.
        if (empty($emails) && $channels === ['email']) {
            Log::warning('alerting: no recipients for severity '.$state->severity, [
                'rule' => $rule->key(),
                'kind' => $kind,
                'hint' => 'assign the role, or set ALERTING_RECIPIENTS in .env',
            ]);

            return;
        }

        $subject = $this->renderSubject($state, $rule, $kind);
        $body = $this->renderBody($state, $rule, $kind);

        $context = [
            'alert_state_id' => $state->id,
            'rule_key' => $rule->key(),
            'kind' => $kind,
            'target_type' => $state->target_type,
            'target_id' => $state->target_id,
            'fire_count' => $state->fire_count,
            'telegram_topic' => $this->topicFor($state, $rule),
            'severity' => $state->severity,
            'description' => $rule->description(),

            // Konteks alert itu sendiri (label, ambang, nilai terukur) ikut
            // dibawa supaya kanal yang bukan surel dapat menyusun pesannya
            // sendiri dari data, bukan dari badan HTML surel.
            //
            // Tanpa ini, Telegram hanya punya badan surel dan terpaksa
            // membuang tag-nya — yang tersisa adalah kerangka tabel berupa
            // baris kosong berlapis, nyaris tak terbaca di ponsel.
            'alert' => $state->context ?? [],
        ];

        try {
            // Surel dikirim ke ORANG; Telegram ke SATU GRUP.
            //
            // Keduanya dipisah karena penerimanya memang berbeda jenis, bukan
            // format berbeda dari hal yang sama. Menyatukannya berarti chat id
            // grup ikut dikirimi surel, atau alamat surel ikut dikirim ke
            // Telegram — dan yang kedua gagal tanpa suara.
            $kanalSurel = array_values(array_intersect($channels, ['email']));
            $kanalLain = array_values(array_diff($channels, ['email']));

            if ($kanalSurel !== [] && $emails !== []) {
                Notify::to(...$emails)
                    ->channel($kanalSurel)
                    ->subject($subject)
                    ->body($body)
                    ->priority($state->severity)
                    ->context($context)
                    ->send();
            }

            foreach ($kanalLain as $kanal) {
                $tujuan = $this->groupRecipientFor($kanal);

                if ($tujuan === null) {
                    // Kanalnya dinyalakan tetapi tujuannya belum diisi. Dicatat,
                    // bukan didiamkan: kanal yang menyala tanpa tujuan terlihat
                    // persis seperti kanal yang bekerja.
                    Log::warning("alerting: kanal '{$kanal}' aktif tetapi tujuannya belum dikonfigurasi", [
                        'rule' => $rule->key(),
                        'severity' => $state->severity,
                    ]);

                    continue;
                }

                Notify::to($tujuan)
                    ->channel([$kanal])
                    ->subject($subject)
                    ->body($body)
                    ->priority($state->severity)
                    ->context($context)
                    ->send();
            }
        } catch (\Throwable $e) {
            // Swallow — never let a notification failure cascade back into
            // the caller (the AlertEvaluator). Log and move on.
            Log::error('alerting: notification dispatch failed', [
                'rule' => $rule->key(),
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Topik Telegram untuk peringatan ini.
     *
     * Yang benar-benar berharga hanyalah memisahkan `critical` dari sisanya:
     * di kotak masuk, 4 peringatan kritis tampak sama persis dengan 405
     * warning di sekelilingnya. Pemisahan berdasarkan asal (keamanan vs
     * sinkronisasi) sekadar merapikan, jadi ia diperiksa SESUDAHNYA.
     *
     * Pemetaan topik → id thread ada di Vault, bukan di sini, supaya menambah
     * topik tidak menuntut rilis paket.
     */
    protected function topicFor(AlertState $state, AlertRuleDefinition $rule): string
    {
        $key = $rule->key();

        // ⚠️ Ketersediaan diperiksa SEBELUM severity, dan itu disengaja.
        //
        // Situs mati memang genting, tetapi jumlahnya berbeda kelas: 29 dari
        // 73 monitor sedang mati sekarang, dan banyak di antaranya sudah mati
        // berminggu-minggu (uptime 30 hari = 0%). Membiarkannya masuk topik
        // Kritis membuat topik itu berisi puluhan pesan tentang hal yang sama
        // tiap hari — dan peringatan yang BENAR-BENAR jarang, seperti agen
        // keamanan mati atau disk hampir habis, ikut tenggelam.
        //
        // Topik terpisah membuat keduanya tetap terbaca: yang satu daftar
        // pantauan, yang satu lagi panggilan bangun.
        if (str_starts_with($key, 'uptime.')) {
            return str_contains(strtolower((string) ($state->context['tag'] ?? '')), 'wifi')
                ? 'wifi'
                : 'ketersediaan';
        }

        // Job antrean yang gagal juga diperiksa SEBELUM severity.
        //
        // Ia ber-severity `critical` dengan sengaja — job yang mati diam-diam
        // mematikan hal lain yang bergantung padanya, dan tidak ada gejala
        // lain yang menandainya. Tetapi TEMPATNYA bukan di Kritis: ini urusan
        // proses latar belakang, sekelompok dengan kegagalan sinkronisasi, dan
        // ditangani orang yang sama.
        //
        // `ScanWordpressJob` yang gagal berulang adalah pekerjaan pemeliharaan,
        // bukan panggilan bangun tengah malam. Membiarkannya di Kritis membuat
        // topik itu berisi hal rutin, dan yang benar-benar jarang — agen
        // keamanan mati, disk hampir habis — ikut tenggelam.
        if (str_starts_with($key, 'queue.')) {
            return 'sinkronisasi';
        }

        if ($state->severity === 'critical') {
            return 'kritis';
        }

        return match (true) {
            str_starts_with($key, 'secscan.') => 'keamanan',
            str_starts_with($key, 'sync.') => 'sinkronisasi',
            str_starts_with($key, 'cloudflare.ssl') => 'sertifikat',
            default => 'pengumuman',
        };
    }

    /**
     * Tujuan untuk kanal yang mengirim ke satu tempat bersama, bukan per orang.
     *
     * Dibaca dari Vault bila tersedia, dengan config sebagai cadangan supaya
     * kanal tetap dapat diuji tanpa Vault terpasang.
     */
    protected function groupRecipientFor(string $channel): ?string
    {
        $tujuan = config("nawasara-alerting.group_recipients.{$channel}");

        if (! $tujuan && class_exists(Vault::class)) {
            try {
                $tujuan = Vault::get($channel, 'chat_id');
            } catch (\Throwable) {
                $tujuan = null;
            }
        }

        return ($tujuan !== null && $tujuan !== '') ? (string) $tujuan : null;
    }

    protected function renderSubject(AlertState $state, AlertRuleDefinition $rule, string $kind): string
    {
        $prefix = match ($kind) {
            'renotified' => '[RE-NOTIFY] ',
            'resolved' => '[RESOLVED] ',
            default => '',
        };

        $template = $rule->subjectTemplate();
        $context = $state->context ?? [];

        // Replace {placeholders} — {severity}, {category}, {target_type},
        // {target_id}, {key}, and {context.X} for dot-notation lookups.
        $subject = preg_replace_callback('/\{([\w\.]+)\}/', function ($m) use ($state, $rule, $context) {
            $key = $m[1];

            return match (true) {
                $key === 'severity' => strtoupper($state->severity),
                $key === 'category' => $rule->category(),
                $key === 'key' => $rule->key(),
                $key === 'target_type' => (string) ($state->target_type ?? ''),
                $key === 'target_id' => (string) ($state->target_id ?? ''),
                Str::startsWith($key, 'context.') => $this->stringify(Arr::get($context, Str::after($key, 'context.'), '')),
                default => $m[0],
            };
        }, $template);

        return $prefix.$subject;
    }

    protected function renderBody(AlertState $state, AlertRuleDefinition $rule, string $kind): string
    {
        $candidates = array_filter([
            $rule->bodyView(),
            "nawasara-alerting::emails.alert-{$kind}",
            'nawasara-alerting::emails.alert-fired',
        ]);

        foreach ($candidates as $view) {
            if (view()->exists($view)) {
                return view($view, [
                    'state' => $state,
                    'rule' => $rule,
                    'kind' => $kind,
                    'context' => $state->context ?? [],
                    'action_url' => $this->actionUrl($state),
                ])->render();
            }
        }

        // Final fallback — plain summary so a missing template doesn't make
        // notifications silently empty.
        return sprintf(
            "%s\n\nRule: %s\nState: %s\nFire count: %d\nLast fired: %s\n\nDetails: %s",
            $rule->description() ?: $rule->key(),
            $rule->key(),
            $state->status,
            $state->fire_count,
            optional($state->fired_at)->toDateTimeString() ?? '-',
            $this->actionUrl($state),
        );
    }

    protected function actionUrl(AlertState $state): string
    {
        $base = rtrim((string) config('nawasara-alerting.email.action_url_base', config('app.url')), '/');

        return $base.'/nawasara-alerting/states?state='.$state->id;
    }

    protected function stringify(mixed $v): string
    {
        if (is_scalar($v)) {
            return (string) $v;
        }
        if ($v === null) {
            return '';
        }

        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
