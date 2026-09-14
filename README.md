# nawasara/alerting

Central incident bus for Nawasara. Register alert rules, dispatch fire/resolve through the `Alerter` facade, run a state machine with cooldown and escalation, and route notifications to `nawasara/notification` channels.

## Install

```bash
composer require nawasara/alerting
php artisan migrate
php artisan db:seed --class="Nawasara\Alerting\Database\Seeders\PermissionSeeder"
```

## Quick start

### 1. Register an alert rule in your package's ServiceProvider

```php
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;

public function boot(): void
{
    Alerter::registerRule(AlertRule::make([
        'key' => 'proxmox.node.disk_critical',
        'severity' => 'critical',
        'category' => 'infrastructure',
        'cooldown_minutes' => 60,
        'description' => 'Node disk usage >= 95%',
        'subject_template' => '[CRITICAL] Disk hampir penuh di {context.node}: {context.disk_pct}%',
    ]));
}
```

### 2. Fire the alert from a sync job or listener

```php
Alerter::fire(
    ruleKey: 'proxmox.node.disk_critical',
    targetType: 'ProxmoxNode',
    targetId: (string) $node->id,
    context: [
        'node' => $node->name,
        'disk_pct' => 96.3,
        'storage' => 'local-lvm',
    ],
);
```

`fire()` is idempotent. Calling it again while the alert is already firing does not send a duplicate notification, because the cooldown gate blocks it. Calling again after the cooldown window triggers a re-notify (an escalation hint).

### 3. Resolve when the underlying condition clears

```php
Alerter::resolve(
    ruleKey: 'proxmox.node.disk_critical',
    targetType: 'ProxmoxNode',
    targetId: (string) $node->id,
);
```

## Permissions

- `alerting.view`: view the dashboard and states
- `alerting.acknowledge`: acknowledge a firing alert (stop re-notify)
- `alerting.resolve`: manually force a state to ok
- `alerting.silence`: silence a state for N minutes
- `alerting.rule.manage`: code-level rule management (developers)

## Sync failure auto-alerting

Any package extending the `nawasara/sync` `AbstractSyncJob` gets sync-failure alerts automatically when retries are exhausted. No manual rule registration is needed. The rule key is `sync.job.failed.{service}`.

## State machine

```
   ┌──────────────────────────────────────────────┐
   │                                              ▼
(none) ──fire──▶ firing ──resolve──▶ ok ──fire──▶ firing
                  │  │
                  │  └─fire (>cooldown)─▶ re-notify (fire_count++)
                  │
                  └─fire (<cooldown)─▶ no-op
```

Acknowledgement and silence are orthogonal modifiers. They suppress re-notify without changing `status`.

## Status

Phase 1 MVP. See `docs/plan-nawasara-alerting-phase-1.md` in the root repo for the sprint-level breakdown.
