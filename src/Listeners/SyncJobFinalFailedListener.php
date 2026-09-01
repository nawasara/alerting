<?php

namespace Nawasara\Alerting\Listeners;

use Illuminate\Support\Str;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;
use Nawasara\Sync\Events\SyncJobFinalFailed;
use Nawasara\Sync\Models\SyncJob;

/**
 * Auto-fire alerting when nawasara/sync exhausts retries. Rule keys are
 * built per-service (sync.job.failed.{service}) so different services have
 * independent cooldowns, and registered lazily here on first sighting —
 * consumer packages get sync-failure alerting for free without any boot
 * registration.
 */
class SyncJobFinalFailedListener
{
    public function handle(SyncJobFinalFailed $event): void
    {
        $tracker = $event->tracker;
        $service = $tracker->service ?: 'unknown';
        $ruleKey = "sync.job.failed.{$service}";

        // Loop-break: if for any reason our own dispatcher's notification
        // path itself triggers a sync job failure, do NOT alert on it —
        // would deadlock the alerter into reentrant fires.
        if (Str::startsWith($service, 'alerting')) {
            return;
        }

        // Lazy register — Alerter::hasRule survives the request lifecycle
        // via singleton registry, so this only does work on the first fire
        // per service per process.
        self::ensureRule($service);

        // errorMessage, bukan exception->getMessage(): $exception tidak dibawa
        // lintas queue (objek Throwable bisa memuat closure di stack trace-nya).
        $error = $event->errorMessage;
        $context = [
            'service' => $service,
            'action' => $tracker->action,
            'target_type' => $tracker->target_type,
            'target_id' => $tracker->target_id,
            'attempts' => $tracker->attempts,
            'sync_job_id' => $tracker->id,
            'error' => $error,
            'error_short' => Str::limit($error, 80, '…'),
        ];

        Alerter::fire(
            ruleKey: $ruleKey,
            targetType: 'SyncJob',
            targetId: self::targetIdFor($tracker),
            context: $context,
        );
    }

    /**
     * Identitas alert = APA yang gagal, bukan PERCOBAAN MANA yang gagal.
     *
     * ⚠️ Dulu ini memakai `$tracker->id` — id baris percobaan, yang berbeda
     * setiap kali job dijalankan. Akibatnya tiap kegagalan melahirkan alert
     * BARU alih-alih menyalakan ulang yang sudah ada, sehingga:
     *
     *   - alert lama tidak pernah bisa pulih (tidak ada yang menunjuk ke sana),
     *   - acknowledge tidak berguna: kegagalan berikutnya membuat state baru
     *     yang belum di-ack,
     *   - dan tiap state menua sendiri sampai batas max_renotify.
     *
     * Di produksi (2 September 2026) itu menumpuk jadi 5.894 state `firing`
     * dan 29.208 surel — 5.278 di antaranya dari satu layanan saja, semuanya
     * dari kegagalan yang sudah lama pulih.
     *
     * Dengan kunci yang stabil, kegagalan berulang pada hal yang sama
     * menyalakan SATU alert (fire_count naik), acknowledge bertahan, dan
     * pemulihannya dapat ditemukan kembali oleh SyncJobSucceededListener.
     */
    /**
     * Daftarkan aturan per-layanan bila belum ada.
     *
     * Ada di SATU tempat dan dipakai kedua listener. Sempat tersalin di jalur
     * sukses, dan salinan seperti itu tidak pernah bertahan: begitu salah satu
     * diubah, cooldown atau severity-nya berbeda tergantung apakah proses itu
     * kebetulan pernah melihat kegagalan lebih dulu — perbedaan yang hampir
     * mustahil dilacak dari gejalanya.
     */
    public static function ensureRule(string $service): void
    {
        $ruleKey = "sync.job.failed.{$service}";

        if (Alerter::hasRule($ruleKey)) {
            return;
        }

        Alerter::registerRule(AlertRule::make([
            'key' => $ruleKey,
            'severity' => config('nawasara-alerting.sync_failure.severity', 'warning'),
            'category' => 'sync',
            'cooldown_minutes' => config('nawasara-alerting.sync_failure.cooldown_minutes', 60),
            'description' => "Sync job for {$service} failed after exhausting retries",
            'subject_template' => "[{severity}] {$service}/{context.action} sync failed: {context.error_short}",
        ]));
    }

    public static function targetIdFor(SyncJob $tracker): string
    {
        return implode(':', [
            $tracker->service ?: 'unknown',
            $tracker->action ?: 'unknown',
            $tracker->target_type ?: '-',
            $tracker->target_id ?: '-',
        ]);
    }
}
