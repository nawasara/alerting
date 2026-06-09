<?php

namespace Nawasara\Alerting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled job that re-notifies firing AlertStates past their cooldown.
 * Sprint 1.4 implements the actual escalation logic.
 */
class EscalateStaleAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Sprint 1.4 implementation.
    }
}
