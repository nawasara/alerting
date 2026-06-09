<?php

namespace Nawasara\Alerting\Services;

use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Exceptions\RuleAlreadyRegistered;

/**
 * In-memory registry of alert rule definitions. Filled at service-provider
 * boot by consumer packages via Alerter::registerRule(). Singleton — same
 * instance survives across the request lifecycle.
 *
 * Rules are intentionally NOT persisted. They are code, not data: a rule
 * change means a deploy. This keeps the registry simple and the audit story
 * obvious (the rule that fired is whatever the code at HEAD declares).
 */
class AlertRuleRegistry
{
    /** @var array<string, AlertRuleDefinition> */
    protected array $rules = [];

    public function register(AlertRuleDefinition $rule): void
    {
        $key = $rule->key();

        if (isset($this->rules[$key])) {
            throw new RuleAlreadyRegistered(
                "Alert rule '{$key}' is already registered. Each rule key must be unique across the application.",
            );
        }

        $this->rules[$key] = $rule;
    }

    /**
     * Register or replace an existing rule silently. Used by listeners that
     * auto-define rules on first sighting (e.g. sync.job.failed.{service})
     * where registering twice during reboot is normal, not an error.
     */
    public function registerOrReplace(AlertRuleDefinition $rule): void
    {
        $this->rules[$rule->key()] = $rule;
    }

    public function get(string $key): ?AlertRuleDefinition
    {
        return $this->rules[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->rules[$key]);
    }

    /**
     * @return array<string, AlertRuleDefinition>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function count(): int
    {
        return count($this->rules);
    }
}
