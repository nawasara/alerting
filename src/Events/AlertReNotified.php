<?php

namespace Nawasara\Alerting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Models\AlertState;

/**
 * Fires when an already-firing state crosses its cooldown threshold and a
 * fresh notification is sent — escalation hint, not a new fire.
 */
class AlertReNotified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AlertState $state,
        public AlertRuleDefinition $rule,
    ) {}
}
