<?php

use Nawasara\Alerting\RecipientGroups\DevelopersGroup;
use Nawasara\Alerting\RecipientGroups\SysadminGroup;

return [
    'scheduler' => [
        'enabled' => env('NAWASARA_ALERTING_SCHEDULER_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Severity routing
    |--------------------------------------------------------------------------
    | Per-severity default audience, channel, and re-notify cooldown.
    | A rule can override cooldown_minutes via AlertRuleDefinition::cooldownMinutes().
    */
    /*
    |--------------------------------------------------------------------------
    | Job antrean gagal — jaring terakhir
    |--------------------------------------------------------------------------
    |
    | Menjaring job yang BUKAN turunan AbstractSyncJob, yang selama ini lolos
    | sepenuhnya. Per 4 September 2026 `failed_jobs` berisi 1.320 baris tanpa
    | satu pun peringatan sepanjang riwayatnya.
    |
    | Severity `critical` disengaja: job yang mati diam-diam mematikan hal lain
    | yang bergantung padanya — SLA berhenti dihitung, peringatan disk penuh
    | hilang — dan tidak ada gejala lain yang menandainya.
    |
    */
    'queue_failure' => [
        'enabled' => env('ALERTING_QUEUE_FAILURE_ENABLED', true),
        'severity' => env('ALERTING_QUEUE_FAILURE_SEVERITY', 'critical'),
        'cooldown_minutes' => (int) env('ALERTING_QUEUE_FAILURE_COOLDOWN', 1440),
    ],

    'severity' => [
        'critical' => [
            'recipient_groups' => ['developers', 'sysadmin'],
            // Surel TETAP jalan berdampingan dengan Telegram — bukan diganti.
            // Telegram menambah kecepatan sampai; surel yang menyimpan jejak.
            // Bila bot dicabut atau grupnya terhapus, peringatan tetap tiba.
            'channels' => ['email', 'telegram'],
            'cooldown_minutes' => 30,
            'default_color' => 'danger',
        ],
        // 'developers' disertakan di ketiga severity, bukan hanya critical.
        // Alasannya praktis: di deployment Ponorogo role 'sysadmin' tidak punya
        // anggota, sehingga setiap alert warning/info berakhir sebagai baris
        // "no recipients for severity" di log dan tidak sampai ke siapa pun.
        // Auto-block IP (secscan.ip.autoblocked) ber-severity warning, jadi
        // seluruh tindakan blokir berlangsung tanpa pemberitahuan.
        //
        // Menambahkan grup di sini lebih aman daripada mengisi extra_recipients
        // dengan email tetap: audiens tetap mengikuti keanggotaan role, jadi
        // orang yang keluar dari tim otomatis berhenti menerima alert.
        'warning' => [
            'recipient_groups' => ['developers', 'sysadmin'],
            // Severity ini yang paling ramai — auto-block IP dan kegagalan
            // sinkronisasi ada di sini. Aman dinyalakan HANYA setelah dua
            // perbaikan 2 September 2026: cooldown auto-block yang tadinya 0
            // (405 blokir → 2.014 pesan) dan kunci alert sync yang berubah
            // tiap percobaan. Tanpa keduanya, grup ini akan ditinggalkan
            // dalam sehari.
            'channels' => ['email', 'telegram'],
            'cooldown_minutes' => 120,
            'default_color' => 'warning',
        ],
        'info' => [
            'recipient_groups' => ['developers', 'sysadmin'],
            'channels' => ['email', 'telegram'],
            'cooldown_minutes' => 360,
            'default_color' => 'info',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recipient groups
    |--------------------------------------------------------------------------
    | Map of group key → RecipientGroup implementation class. Resolved at
    | dispatch time so the most current role-based audience is always used.
    */
    'recipient_groups' => [
        'developers' => DevelopersGroup::class,
        'sysadmin' => SysadminGroup::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra recipients (e-mail, no account needed)
    |--------------------------------------------------------------------------
    | Addresses added on top of the role-based groups above. Use these for
    | shared mailboxes and people without a Nawasara account (CSIRT, kepala
    | dinas, vendor) — and as a safety net when a role has no members yet, so
    | alerts still reach someone.
    |
    | Comma-separated env, e.g.
    |   ALERTING_RECIPIENTS=csirt@ponorogo.go.id,kominfo@ponorogo.go.id
    |   ALERTING_RECIPIENTS_CRITICAL=kadis@ponorogo.go.id
    */
    'extra_recipients' => [
        // every severity
        'all' => array_filter(array_map('trim', explode(',', (string) env('ALERTING_RECIPIENTS', '')))),
        // per-severity additions
        'critical' => array_filter(array_map('trim', explode(',', (string) env('ALERTING_RECIPIENTS_CRITICAL', '')))),
        'warning' => array_filter(array_map('trim', explode(',', (string) env('ALERTING_RECIPIENTS_WARNING', '')))),
        'info' => array_filter(array_map('trim', explode(',', (string) env('ALERTING_RECIPIENTS_INFO', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalation
    |--------------------------------------------------------------------------
    | Recurring scan of firing alerts that have exceeded their cooldown
    | without acknowledgement — re-notify (escalation hint) up to N times.
    */
    'escalation' => [
        'enabled' => env('NAWASARA_ALERTING_ESCALATION_ENABLED', true),
        'scan_interval_minutes' => 15,
        'max_renotify_per_alert' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync failure auto-rule
    |--------------------------------------------------------------------------
    | Listener auto-registers sync.job.failed.{service} on first fire so
    | consumer packages don't have to register the rule themselves.
    */
    'sync_failure' => [
        'enabled' => env('NAWASARA_ALERTING_SYNC_FAILURE_ENABLED', true),
        'severity' => 'warning',
        'cooldown_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    | Where action links in the email point. Defaults to APP_URL; override
    | when running on a different host than the public-facing one.
    */
    'email' => [
        'action_url_base' => env('NAWASARA_ALERTING_ACTION_URL_BASE', env('APP_URL')),
        'from' => [
            'address' => env('NAWASARA_ALERTING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'name' => env('NAWASARA_ALERTING_FROM_NAME', env('MAIL_FROM_NAME', 'Nawasara Alerting')),
        ],
    ],
];
