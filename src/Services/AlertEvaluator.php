<?php

namespace Nawasara\Alerting\Services;

use Illuminate\Support\Facades\DB;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Events\AlertFired;
use Nawasara\Alerting\Events\AlertReNotified;
use Nawasara\Alerting\Events\AlertResolved;
use Nawasara\Alerting\Exceptions\UnknownAlertRule;
use Nawasara\Alerting\Models\AlertState;

/**
 * The state machine. Idempotent across calls — same fire() invocation
 * twice within the cooldown is a no-op, so callers (sync jobs that detect
 * threshold breach every minute) don't need to track "did I already alert
 * about this".
 *
 * Transitions:
 *   (none)         + fire    → INSERT firing, notify
 *   ok             + fire    → UPDATE firing, notify (re-fired after resolve)
 *   firing (cold)  + fire    → UPDATE last_notified_at + fire_count, notify (escalation)
 *   firing (warm)  + fire    → no-op (cooldown not yet elapsed)
 *   firing + ack/silence + fire (anywhere) → no-op (suppressed)
 *   firing         + resolve → UPDATE ok, notify
 *   ok             + resolve → no-op
 */
class AlertEvaluator
{
    public function __construct(
        protected AlertRuleRegistry $registry,
        protected NotifyDispatcher $dispatcher,
    ) {}

    /**
     * Fire an alert against the given target. Idempotent.
     *
     * @return AlertState The state after this call (firing in all happy paths)
     */
    public function fire(
        string $ruleKey,
        ?string $targetType,
        ?string $targetId,
        array $context = [],
    ): AlertState {
        $rule = $this->resolveRule($ruleKey);
        $cooldown = $this->effectiveCooldown($rule);

        return DB::transaction(function () use ($rule, $targetType, $targetId, $context, $cooldown) {
            // Lock the natural-key row (or absence) for the duration of
            // the transition so two parallel fire() calls don't both
            // create-or-update racing each other.
            $state = AlertState::query()
                ->where('rule_key', $rule->key())
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                // Initial fire.
                $state = AlertState::create([
                    'rule_key' => $rule->key(),
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'status' => 'firing',
                    'severity' => $rule->severity(),
                    'fired_at' => now(),
                    'last_notified_at' => now(),
                    'fire_count' => 1,
                    'context' => $context,
                ]);

                $this->dispatcher->dispatch($state, $rule, 'fired');
                AlertFired::dispatch($state, $rule);

                return $state;
            }

            // Existing state — figure out what kind of transition.
            $mergedContext = array_merge($state->context ?? [], $context);

            if ($state->isResolved()) {
                // ok → firing (re-fired after a previous resolve).
                $state->update([
                    'status' => 'firing',
                    'severity' => $rule->severity(),
                    'fired_at' => now(),
                    'resolved_at' => null,
                    'last_notified_at' => now(),
                    'acknowledged_at' => null,
                    'acknowledged_by' => null,
                    'fire_count' => $state->fire_count + 1,
                    'context' => $mergedContext,
                ]);

                $this->dispatcher->dispatch($state, $rule, 'fired');
                AlertFired::dispatch($state, $rule);

                return $state;
            }

            // status='firing' below.
            if ($state->isAcknowledged() || $state->isSilenced()) {
                // Suppressed — just update context so the next UI render
                // shows the latest snapshot, but don't notify.
                $state->update(['context' => $mergedContext]);

                return $state;
            }

            if (! $state->shouldRenotify($cooldown)) {
                // Cooldown still warm. Touch context only.
                $state->update(['context' => $mergedContext]);

                return $state;
            }

            // firing + cooldown elapsed → escalation re-notify.
            $state->update([
                'last_notified_at' => now(),
                'fire_count' => $state->fire_count + 1,
                'context' => $mergedContext,
            ]);

            $this->dispatcher->dispatch($state, $rule, 'renotified');
            AlertReNotified::dispatch($state, $rule);

            return $state;
        });
    }

    /**
     * Resolve a firing alert. Idempotent: resolving an already-resolved or
     * unknown state is a no-op (returns null in the latter case).
     */
    public function resolve(
        string $ruleKey,
        ?string $targetType,
        ?string $targetId,
    ): ?AlertState {
        $rule = $this->resolveRule($ruleKey);

        return DB::transaction(function () use ($rule, $targetType, $targetId) {
            $state = AlertState::query()
                ->where('rule_key', $rule->key())
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->lockForUpdate()
                ->first();

            if ($state === null || $state->isResolved()) {
                return $state;
            }

            $state->update([
                'status' => 'ok',
                'resolved_at' => now(),
            ]);

            $this->dispatcher->dispatch($state, $rule, 'resolved');
            AlertResolved::dispatch($state, $rule);

            return $state;
        });
    }

    /**
     * Effective cooldown = rule override (if set) ELSE severity default.
     * Public so the escalation job uses the same computation.
     */
    public function effectiveCooldown(AlertRuleDefinition $rule): int
    {
        $ruleCd = $rule->cooldownMinutes();
        if ($ruleCd !== null && $ruleCd > 0) {
            return $ruleCd;
        }

        return (int) config(
            "nawasara-alerting.severity.{$rule->severity()}.cooldown_minutes",
            60,
        );
    }

    protected function resolveRule(string $ruleKey): AlertRuleDefinition
    {
        $rule = $this->registry->get($ruleKey);
        if ($rule === null) {
            throw new UnknownAlertRule(
                "No alert rule registered for key '{$ruleKey}'. Register it in your package's ServiceProvider::boot() via Alerter::registerRule().",
            );
        }

        return $rule;
    }
}
