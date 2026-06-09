<?php

namespace Nawasara\Alerting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Models\AlertState;

/**
 * Fires when a firing state transitions to ok — either via Alerter::resolve
 * or manual UI action.
 */
class AlertResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AlertState $state,
        public AlertRuleDefinition $rule,
    ) {}
}
