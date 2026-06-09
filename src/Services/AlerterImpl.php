<?php

namespace Nawasara\Alerting\Services;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Models\AlertState;

/**
 * Public API behind the Alerter facade. Thin wrapper that hands off
 * heavy lifting to AlertRuleRegistry + AlertEvaluator + NotifyDispatcher.
 *
 * Consumer packages should call this — not the evaluator directly — so
 * future API tweaks have one stable surface.
 */
class AlerterImpl
{
    public function __construct(
        protected AlertRuleRegistry $registry,
        protected AlertEvaluator $evaluator,
    ) {}

    public function registerRule(AlertRuleDefinition $rule): void
    {
        $this->registry->register($rule);
    }

    public function registerOrReplaceRule(AlertRuleDefinition $rule): void
    {
        $this->registry->registerOrReplace($rule);
    }

    public function hasRule(string $key): bool
    {
        return $this->registry->has($key);
    }

    public function fire(
        string $ruleKey,
        ?string $targetType = null,
        ?string $targetId = null,
        array $context = [],
    ): AlertState {
        return $this->evaluator->fire($ruleKey, $targetType, $targetId, $context);
    }

    public function resolve(
        string $ruleKey,
        ?string $targetType = null,
        ?string $targetId = null,
    ): ?AlertState {
        return $this->evaluator->resolve($ruleKey, $targetType, $targetId);
    }

    public function isFiring(
        string $ruleKey,
        ?string $targetType = null,
        ?string $targetId = null,
    ): bool {
        return AlertState::query()
            ->where('rule_key', $ruleKey)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('status', 'firing')
            ->exists();
    }

    /**
     * @return Collection<int, AlertState>
     */
    public function firing(): Collection
    {
        return AlertState::firing()->orderByDesc('fired_at')->get();
    }

    /**
     * Acknowledge a firing alert. Doesn't change status, just stops
     * re-notification. Caller is "I see this, I'm working on it."
     */
    public function acknowledge(int $stateId, ?User $user = null): AlertState
    {
        /** @var AlertState $state */
        $state = AlertState::query()->lockForUpdate()->findOrFail($stateId);

        $state->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user?->getKey() ?? auth()->id(),
        ]);

        return $state;
    }

    public function silence(
        int $stateId,
        int $minutes,
        ?User $user = null,
        ?string $reason = null,
    ): AlertState {
        /** @var AlertState $state */
        $state = AlertState::query()->lockForUpdate()->findOrFail($stateId);

        $state->update([
            'silenced_until' => now()->addMinutes($minutes),
            'silenced_by' => $user?->getKey() ?? auth()->id(),
            'silenced_reason' => $reason,
        ]);

        return $state;
    }

    /**
     * Manually force a state to ok. Use when a sysadmin knows the
     * underlying condition cleared but the auto-detection won't run.
     */
    public function forceResolve(int $stateId): ?AlertState
    {
        /** @var AlertState $state */
        $state = AlertState::query()->lockForUpdate()->findOrFail($stateId);

        if ($state->isResolved()) {
            return $state;
        }

        $rule = $this->registry->get($state->rule_key);
        if ($rule !== null) {
            return $this->evaluator->resolve($state->rule_key, $state->target_type, $state->target_id);
        }

        // Rule unknown (consumer package removed?) — fall back to a raw
        // state flip so the sysadmin can still clear the noise.
        $state->update([
            'status' => 'ok',
            'resolved_at' => now(),
        ]);

        return $state;
    }
}
