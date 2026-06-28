<?php

$prefix = 'nawasara-alerting';

return [
    [
        'workspace' => 'monitoring',
        'label' => 'Monitoring',
        'icon' => 'lucide-activity',
        'group' => 'Observability',
        'url' => '',
        'permission' => 'alerting.view',
        'submenu' => [
            [
                'label' => 'Alerting',
                'icon' => 'lucide-bell-ring',
                'url' => url($prefix.'/dashboard'),
                'permission' => 'alerting.view',
                'navigate' => true,
            ],
            [
                'label' => 'Alert States',
                'icon' => 'lucide-list-checks',
                'url' => url($prefix.'/states'),
                'permission' => 'alerting.view',
                'navigate' => true,
            ],
        ],
    ],
];
