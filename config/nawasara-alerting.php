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
            'channels' => ['email'],
            'cooldown_minutes' => 30,
            'default_color' => 'danger',
        ],
        'warning' => [
            'recipient_groups' => ['sysadmin'],
            'channels' => ['email'],
            'cooldown_minutes' => 120,
            'default_color' => 'warning',
        ],
        'info' => [
            'recipient_groups' => ['sysadmin'],
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
