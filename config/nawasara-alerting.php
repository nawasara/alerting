<?php

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
    'severity' => [
        'critical' => [
            'recipient_groups' => ['developers', 'sysadmin'],
            // Telegram dinyalakan untuk `critical` LEBIH DULU, sendirian.
            //
            // Ini severity dengan jumlah paling sedikit, jadi bila ada yang
            // keliru — topik tertukar, pesan terlalu panjang, bot dikeluarkan
            // dari grup — yang terlanjur terkirim juga sedikit. Menyalakan
            // semuanya sekaligus membuat kekeliruan kecil menjadi ratusan
            // pesan sebelum sempat disadari.
            //
            // `warning` menyusul setelah ini terbukti tenang. Surel TETAP
            // jalan: Telegram menambah kecepatan sampai, bukan mengganti jejak.
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
            'channels' => ['email'],
            'cooldown_minutes' => 120,
            'default_color' => 'warning',
        ],
        'info' => [
            'recipient_groups' => ['developers', 'sysadmin'],
            'channels' => ['email'],
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
        'developers' => \Nawasara\Alerting\RecipientGroups\DevelopersGroup::class,
        'sysadmin' => \Nawasara\Alerting\RecipientGroups\SysadminGroup::class,
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
