<?php

namespace Nawasara\Alerting\Services;

use Illuminate\Support\Collection;
use Nawasara\Alerting\Contracts\RecipientGroup;

/**
 * Resolves "who should this severity be sent to" — severity → group keys
 * (from config) → RecipientGroup instances → flat deduped user collection.
 */
class RecipientResolver
{
    /**
     * @return Collection<int, \Illuminate\Foundation\Auth\User>
     */
    public function resolveBySeverity(string $severity): Collection
    {
        $groupKeys = config("nawasara-alerting.severity.{$severity}.recipient_groups", []);

        $users = collect();
        foreach ($groupKeys as $key) {
            $users = $users->merge($this->resolveByGroupKey($key));
        }

        return $users->unique('id')->values();
    }

    /**
     * @return Collection<int, \Illuminate\Foundation\Auth\User>
     */
    public function resolveByGroupKey(string $key): Collection
    {
        $class = config("nawasara-alerting.recipient_groups.{$key}");
        if (! $class || ! class_exists($class)) {
            return collect();
        }

        /** @var RecipientGroup $group */
        $group = app($class);

        return $group->resolve();
    }

    /**
     * List all available group keys (for UI dropdowns, etc.).
     *
     * @return list<string>
     */
    public function availableGroupKeys(): array
    {
        return array_keys(config('nawasara-alerting.recipient_groups', []));
    }
}
