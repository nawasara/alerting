<?php

namespace Nawasara\Alerting\Livewire\States;

use Livewire\Component;

/**
 * Alert states list + acknowledge/resolve/silence actions.
 * Sprint 1.5 implementation.
 */
class Index extends Component
{
    public function render()
    {
        return view('nawasara-alerting::livewire.pages.states.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
