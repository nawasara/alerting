<?php

namespace Nawasara\Alerting\Livewire\Dashboard;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Alerting\Models\AlertState;

class Index extends Component
{
    #[Computed]
    public function firingCounts(): array
    {
        return AlertState::query()
            ->firing()
            ->selectRaw('severity, COUNT(*) as c')
            ->groupBy('severity')
            ->pluck('c', 'severity')
            ->all();
    }

    #[Computed]
    public function totalFiring(): int
    {
        return AlertState::firing()->count();
    }

    #[Computed]
    public function acknowledgedToday(): int
    {
        return AlertState::firing()
            ->whereDate('acknowledged_at', today())
            ->count();
    }

    #[Computed]
    public function resolvedToday(): int
    {
        return AlertState::resolved()
            ->whereDate('resolved_at', today())
            ->count();
    }

    #[Computed]
    public function recentFiring()
    {
        return AlertState::firing()
            ->orderByDesc('fired_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function recentResolved()
    {
        return AlertState::resolved()
            ->whereNotNull('resolved_at')
            ->orderByDesc('resolved_at')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('nawasara-alerting::livewire.pages.dashboard.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
