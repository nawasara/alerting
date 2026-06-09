<?php

namespace Nawasara\Alerting\Services;

/**
 * In-memory registry of alert rule definitions. Filled at service-provider
 * boot by consumer packages via Alerter::registerRule(). Singleton — same
 * instance survives across the request lifecycle.
 *
 * Sprint 1.2 will flesh this out (register/get/all/has + duplicate guard).
 */
class AlertRuleRegistry
{
    /** @var array<string, object> */
    protected array $rules = [];
}
