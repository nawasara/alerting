<?php

namespace Nawasara\Alerting\Models;

use Nawasara\Alerting\Contracts\AlertRuleDefinition;

/**
 * Tiny config DTO implementation of AlertRuleDefinition. Most consumer
 * packages won't need anything richer — just describe the rule once at
 * boot time:
 *
 *   Alerter::registerRule(AlertRule::make([
 *       'key' => 'proxmox.node.disk_critical',
 *       'severity' => 'critical',
 *       'category' => 'infrastructure',
 *       'cooldown_minutes' => 60,
 *       'description' => 'Node disk usage ≥ 95%',
 *       'subject_template' => '[CRITICAL] Disk {context.disk_pct}% on {context.node}',
 *   ]));
 *
 * Reach for a custom class only when severity / cooldown need to be
 * computed per-fire (rare).
 */
class AlertRule implements AlertRuleDefinition
{
    public function __construct(
        protected string $key,
        protected string $severity,
        protected string $category = 'general',
        protected ?int $cooldownMinutes = null,
        protected string $description = '',
        protected string $subjectTemplate = '[{severity}] {key}',
        protected ?string $bodyView = null,
    ) {
        if (! in_array($severity, ['critical', 'warning', 'info'], true)) {
            throw new \InvalidArgumentException(
                "Invalid severity '{$severity}' for rule '{$key}' — must be critical|warning|info",
            );
        }
    }

    /**
     * @param  array{
     *   key:string, severity:string, category?:string,
     *   cooldown_minutes?:?int, description?:string,
     *   subject_template?:string, body_view?:?string
     * }  $attrs
     */
    public static function make(array $attrs): self
    {
        return new self(
            key: $attrs['key'],
            severity: $attrs['severity'],
            category: $attrs['category'] ?? 'general',
            cooldownMinutes: $attrs['cooldown_minutes'] ?? null,
            description: $attrs['description'] ?? '',
            subjectTemplate: $attrs['subject_template'] ?? '[{severity}] '.$attrs['key'],
            bodyView: $attrs['body_view'] ?? null,
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function cooldownMinutes(): ?int
    {
        return $this->cooldownMinutes;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function subjectTemplate(): string
    {
        return $this->subjectTemplate;
    }

    public function bodyView(): ?string
    {
        return $this->bodyView;
    }
}
