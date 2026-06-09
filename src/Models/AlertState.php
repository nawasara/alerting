<?php

namespace Nawasara\Alerting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (rule_key, target_type, target_id) — the central state for
 * "is this alert currently firing, and when did we last yell about it".
 *
 * AlertEvaluator is the only writer in normal flow; callers go through the
 * Alerter facade. Direct ::create() on this model bypasses notification
 * routing and should be avoided outside tests.
 */
class AlertState extends Model
{
    protected $table = 'nawasara_alert_states';

    protected $fillable = [
        'rule_key', 'target_type', 'target_id',
        'status', 'severity',
        'fired_at', 'resolved_at', 'last_notified_at',
        'acknowledged_at', 'acknowledged_by',
        'silenced_until', 'silenced_by', 'silenced_reason',
        'fire_count', 'context',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'silenced_until' => 'datetime',
        'fire_count' => 'integer',
        'context' => 'array',
    ];

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(\Illuminate\Foundation\Auth\User::class, 'acknowledged_by');
    }

    public function silencedBy(): BelongsTo
    {
        return $this->belongsTo(\Illuminate\Foundation\Auth\User::class, 'silenced_by');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeFiring(Builder $query): Builder
    {
        return $query->where('status', 'firing');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'ok');
    }

    public function scopeAcknowledged(Builder $query): Builder
    {
        return $query->whereNotNull('acknowledged_at');
    }

    public function scopeSilenced(Builder $query): Builder
    {
        return $query->whereNotNull('silenced_until')
            ->where('silenced_until', '>', now());
    }

    /**
     * Firing states where last_notified_at is older than the given cooldown
     * — eligible for re-notify (escalation).
     */
    public function scopeStale(Builder $query, int $cooldownMinutes): Builder
    {
        return $query->firing()
            ->where(function (Builder $q) use ($cooldownMinutes) {
                $q->whereNull('last_notified_at')
                    ->orWhere('last_notified_at', '<=', now()->subMinutes($cooldownMinutes));
            });
    }

    // ─── State predicates ───────────────────────────────

    public function isFiring(): bool
    {
        return $this->status === 'firing';
    }

    public function isResolved(): bool
    {
        return $this->status === 'ok';
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function isSilenced(): bool
    {
        return $this->silenced_until !== null
            && $this->silenced_until->isFuture();
    }

    /**
     * Should this firing alert get a re-notify right now? Considers
     * acknowledgement, silence window, and the rule's effective cooldown.
     */
    public function shouldRenotify(int $cooldownMinutes): bool
    {
        if (! $this->isFiring()) {
            return false;
        }

        if ($this->isAcknowledged() || $this->isSilenced()) {
            return false;
        }

        if ($this->last_notified_at === null) {
            return true;
        }

        return $this->last_notified_at->lte(now()->subMinutes($cooldownMinutes));
    }
}
