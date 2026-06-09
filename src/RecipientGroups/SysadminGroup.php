<?php

namespace Nawasara\Alerting\RecipientGroups;

use App\Models\User;
use Illuminate\Support\Collection;
use Nawasara\Alerting\Contracts\RecipientGroup;

class SysadminGroup implements RecipientGroup
{
    public function key(): string
    {
        return 'sysadmin';
    }

    public function label(): string
    {
        return 'Sysadmin';
    }

    public function resolve(): Collection
    {
        return User::role('sysadmin')->get();
    }
}
