# nawasara/alerting

Central incident bus for Nawasara — register alert rules, dispatch
fire/resolve via the `Alerter` facade, state-machine with cooldown +
escalation, routes notifications to `nawasara/notification` channels.

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
        'description' => 'Node disk usage ≥ 95%',
        'subject_template' => '[CRITICAL] Disk hampir penuh di {context.node}: {context.disk_pct}%',
    ]));
}
```

### 2. Fire the alert from a sync job / listener

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

`fire()` is idempotent — calling it again while the alert is already
firing does not send a duplicate notification (cooldown gate). Calling
again after the cooldown window triggers a re-notify (escalation hint).

### 3. Resolve when the underlying condition clears

```php
Alerter::resolve(
    ruleKey: 'proxmox.node.disk_critical',
    targetType: 'ProxmoxNode',
    targetId: (string) $node->id,
);
```

## Permissions

- `alerting.view` — view dashboard + states
- `alerting.acknowledge` — acknowledge a firing alert (stop re-notify)
- `alerting.resolve` — manually force a state to ok
- `alerting.silence` — silence a state for N minutes
- `alerting.rule.manage` — code-level rule management (developers)

## Sync failure auto-alerting

Any package extending `nawasara/sync` `AbstractSyncJob` automatically
gets sync-failure alerts when retries are exhausted — no manual rule
registration needed. Rule key is `sync.job.failed.{service}`.

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

Acknowledgement and silence are orthogonal modifiers — they suppress
re-notify without changing `status`.

## Status

Fase 1 MVP — see `docs/plan-nawasara-alerting-phase-1.md` in the root
repo for the sprint-level breakdown.
