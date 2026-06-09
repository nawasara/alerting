<?php

namespace Nawasara\Alerting\Livewire\Dashboard;

use Livewire\Component;

/**
 * Alerting dashboard — stat cards + firing list + 7-day trend.
 * Sprint 1.5 implementation.
 */
class Index extends Component
{
    public function render()
    {
        return view('nawasara-alerting::livewire.pages.dashboard.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
