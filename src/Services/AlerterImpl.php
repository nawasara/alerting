<?php

namespace Nawasara\Alerting\Services;

/**
 * Public API implementation behind the Alerter facade. Sprint 1.3 fills in
 * fire/resolve/acknowledge/silence/registerRule methods.
 */
class AlerterImpl
{
    public function __construct(
        protected AlertRuleRegistry $registry,
    ) {}
}
