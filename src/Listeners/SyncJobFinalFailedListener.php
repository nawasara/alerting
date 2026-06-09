<?php

namespace Nawasara\Alerting\Listeners;

use Nawasara\Sync\Events\SyncJobFinalFailed;

/**
 * Auto-fire an alert when nawasara/sync marks a job as final-failed (retry
 * exhausted). Rule key is built lazily as sync.job.failed.{service} so
 * consumer packages don't have to register anything — they get sync
 * failure alerting for free.
 *
 * Sprint 1.4 implements the actual handle() logic.
 */
class SyncJobFinalFailedListener
{
    public function handle(SyncJobFinalFailed $event): void
    {
        // Sprint 1.4 implementation.
    }
}
