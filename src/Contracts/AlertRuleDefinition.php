<?php

namespace Nawasara\Alerting\Contracts;

/**
 * What a consumer package registers to make a rule fireable. Implementations
 * can be a tiny anonymous class (test fixture), a configurable DTO
 * (AlertRule::make()), or a richer class that pulls dynamic data per fire
 * (e.g. read severity from a per-target config row).
 */
interface AlertRuleDefinition
{
    public function key(): string;

    /** 'critical' | 'warning' | 'info' */
    public function severity(): string;

    /** Free-form tag — 'infrastructure', 'security', 'business', etc. */
    public function category(): string;

    /**
     * Per-rule cooldown in minutes; null means inherit the severity default
     * (config 'severity.{severity}.cooldown_minutes').
     */
    public function cooldownMinutes(): ?int;

    public function description(): string;

    /**
     * Subject line template. Placeholders:
     *   {severity}, {category}, {target_type}, {target_id},
     *   {context.X} (dot-notation into the fire() context array)
     */
    public function subjectTemplate(): string;

    /**
     * Optional Blade view to render the email body. When null, the package
     * default (alerting::emails.alert-fired) is used.
     */
    public function bodyView(): ?string;
}
