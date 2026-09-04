<?php

namespace Nawasara\Alerting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Nawasara\Alerting\Events\AlertReNotified;
use Nawasara\Alerting\Models\AlertState;
use Nawasara\Alerting\Services\AlertEvaluator;
use Nawasara\Alerting\Services\AlertRuleRegistry;
use Nawasara\Alerting\Services\NotifyDispatcher;

/**
 * Scheduled re-notify pass for firing alerts that have crossed their
 * cooldown without acknowledgement. Runs every N minutes (config
 * escalation.scan_interval_minutes).
 *
 * Behaves like a poor-man's PagerDuty escalation: the louder the alert
 * lingers, the higher fire_count goes — UI can then surface
 * "fire_count > 5" as visually distinct (this rule has been on fire for
 * hours and nobody's looked at it yet).
 *
 * Max re-notify cap (config escalation.max_renotify_per_alert) prevents
 * an unattended alert from spamming forever.
 */
class EscalateStaleAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        AlertRuleRegistry $registry,
        NotifyDispatcher $dispatcher,
        AlertEvaluator $evaluator,
    ): void {
        if (! config('nawasara-alerting.escalation.enabled', true)) {
            return;
        }

        $maxRenotify = (int) config('nawasara-alerting.escalation.max_renotify_per_alert', 5);

        // Pull all firing states. We compute per-rule cooldown in PHP since
        // each rule may override (the SQL "stale" scope can't know rule
        // overrides without joining a non-existent rule table).
        $firing = AlertState::query()
            ->firing()
            ->orderBy('last_notified_at')
            ->get();

        $renotified = 0;
        $skipped = 0;
        $unknown = 0;

        foreach ($firing as $state) {
            if ($state->isAcknowledged() || $state->isSilenced()) {
                $skipped++;

                continue;
            }

            // Cap: once fire_count exceeds the configured max, stop
            // re-notifying. Sysadmin still sees the firing badge in UI.
            if ($state->fire_count >= $maxRenotify) {
                $skipped++;

                continue;
            }

            $rule = $registry->get($state->rule_key);
            if ($rule === null) {
                // The rule was unregistered (consumer package removed?) —
                // we can't compute its cooldown or render notifications.
                // Skip and log; sysadmin can manually forceResolve from UI.
                $unknown++;

                continue;
            }

            $cooldown = $evaluator->effectiveCooldown($rule);
            if (! $state->shouldRenotify($cooldown)) {
                $skipped++;

                continue;
            }

            $state->update([
                'last_notified_at' => now(),
                'fire_count' => $state->fire_count + 1,
            ]);

            $dispatcher->dispatch($state, $rule, 'renotified');
            AlertReNotified::dispatch($state, $rule);

            $renotified++;
        }

        Log::info('alerting: escalation pass complete', [
            'firing_total' => $firing->count(),
            'renotified' => $renotified,
            'skipped' => $skipped,
            'unknown_rule' => $unknown,
        ]);
    }
}
