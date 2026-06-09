<?php

namespace Nawasara\Alerting\RecipientGroups;

use App\Models\User;
use Illuminate\Support\Collection;
use Nawasara\Alerting\Contracts\RecipientGroup;

class DevelopersGroup implements RecipientGroup
{
    public function key(): string
    {
        return 'developers';
    }

    public function label(): string
    {
        return 'Developers';
    }

    public function resolve(): Collection
    {
        return User::role('developer')->get();
    }
}
