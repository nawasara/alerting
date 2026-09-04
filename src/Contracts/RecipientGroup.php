<?php

namespace Nawasara\Alerting\Contracts;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;

/**
 * Maps a config-side group identifier ('developers', 'sysadmin', etc.) to
 * a runtime list of recipient users. Resolved at dispatch time so the
 * latest role/membership state is always used (no stale cache).
 */
interface RecipientGroup
{
    public function key(): string;

    public function label(): string;

    /**
     * @return Collection<int, User>
     */
    public function resolve(): Collection;
}
