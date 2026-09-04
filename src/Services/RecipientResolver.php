<?php

namespace Nawasara\Alerting\Services;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Nawasara\Alerting\Contracts\RecipientGroup;
use Nawasara\Core\Models\Setting;

/**
 * Resolves "who should this severity be sent to" — severity → group keys
 * (from config) → RecipientGroup instances → flat deduped user collection.
 */
class RecipientResolver
{
    /**
     * @return Collection<int, User>
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
     * @return Collection<int, User>
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

    /**
     * Extra e-mail addresses for a severity, configured directly rather than via
     * a role. Role-based groups only reach people who have a Nawasara account;
     * these cover shared mailboxes and outside stakeholders (CSIRT, kepala
     * dinas, vendor) — and act as a safety net when a role has no members.
     *
     * Sources, merged and deduped:
     *   - nawasara-alerting.extra_recipients.all        (every severity)
     *   - nawasara-alerting.extra_recipients.{severity} (that severity only)
     *
     * @return list<string>
     */
    public function extraEmailsBySeverity(string $severity): array
    {
        // UI-managed list (nawasara_settings) wins over the env/config default,
        // so operators can change the audience without a deploy.
        $fromSetting = null;
        if (class_exists(Setting::class)) {
            try {
                $fromSetting = Setting::get('alerting.extra_recipients', null);
            } catch (\Throwable $e) {
                $fromSetting = null; // DB not ready (e.g. during install) — fall back.
            }
        }
        if (is_string($fromSetting)) {
            $fromSetting = preg_split('/[\s,;]+/', $fromSetting) ?: [];
        }

        $all = $fromSetting !== null && $fromSetting !== []
            ? (array) $fromSetting
            : (array) config('nawasara-alerting.extra_recipients.all', []);

        $forSeverity = (array) config("nawasara-alerting.extra_recipients.{$severity}", []);

        return collect($all)
            ->merge($forSeverity)
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
