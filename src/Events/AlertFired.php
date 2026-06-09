<?php

namespace Nawasara\Alerting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Models\AlertState;

/**
 * Fires after a transition INTO firing — both initial fire and re-fire
 * after a previous resolve.
 */
class AlertFired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AlertState $state,
        public AlertRuleDefinition $rule,
    ) {}
}
