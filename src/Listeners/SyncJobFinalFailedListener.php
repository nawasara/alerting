<?php

namespace Nawasara\Alerting\Listeners;

use Illuminate\Support\Str;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;
use Nawasara\Sync\Events\SyncJobFinalFailed;

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
        if (! Alerter::hasRule($ruleKey)) {
            Alerter::registerRule(AlertRule::make([
                'key' => $ruleKey,
                'severity' => config('nawasara-alerting.sync_failure.severity', 'warning'),
                'category' => 'sync',
                'cooldown_minutes' => config('nawasara-alerting.sync_failure.cooldown_minutes', 60),
                'description' => "Sync job for {$service} failed after exhausting retries",
                'subject_template' => "[{severity}] {$service}/{context.action} sync failed: {context.error_short}",
            ]));
        }

        $error = $event->exception->getMessage();
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
            targetId: (string) $tracker->id,
            context: $context,
        );
    }
}
