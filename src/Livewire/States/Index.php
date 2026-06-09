<?php

namespace Nawasara\Alerting\Livewire\States;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertState;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;

class Index extends Component
{
    use WithPagination, WithSudo;

    #[Url]
    public string $statusFilter = 'firing';

    #[Url]
    public string $severityFilter = '';

    #[Url]
    public string $search = '';

    /** Currently inspected state id (null = no modal). */
    public ?int $detailId = null;

    /** Silence form input (minutes). */
    public int $silenceMinutes = 60;
    public ?string $silenceReason = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSeverityFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        return AlertState::query()
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->severityFilter !== '', fn ($q) => $q->where('severity', $this->severityFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($w) {
                    $w->where('rule_key', 'like', '%'.$this->search.'%')
                        ->orWhere('target_type', 'like', '%'.$this->search.'%')
                        ->orWhere('target_id', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByRaw("CASE WHEN status='firing' THEN 0 ELSE 1 END")
            ->orderByDesc('fired_at')
            ->paginate(25);
    }

    #[Computed]
    public function detail(): ?AlertState
    {
        if ($this->detailId === null) {
            return null;
        }

        return AlertState::query()
            ->with(['acknowledgedBy:id,name', 'silencedBy:id,name'])
            ->find($this->detailId);
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->silenceMinutes = 60;
        $this->silenceReason = null;
        $this->dispatch('modal-open:alerting-state-detail');
    }

    public function closeDetail(): void
    {
        $this->dispatch('modal-close:alerting-state-detail');
        $this->detailId = null;
    }

    public function acknowledgeAction(int $id): void
    {
        $this->authorize('alerting.acknowledge');
        Alerter::acknowledge($id);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Alert acknowledged.']);
    }

    #[RequiresSudo(reason: 'force-resolving an alert')]
    public function resolveAction(int $id): void
    {
        $this->authorize('alerting.resolve');
        Alerter::forceResolve($id);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Alert resolved.']);
        $this->closeDetail();
    }

    public function silenceAction(int $id): void
    {
        $this->authorize('alerting.silence');

        if ($this->silenceMinutes < 1) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Silence duration must be at least 1 minute.']);

            return;
        }

        Alerter::silence($id, $this->silenceMinutes, null, $this->silenceReason);

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => "Alert silenced for {$this->silenceMinutes} minutes.",
        ]);
    }

    public function render()
    {
        return view('nawasara-alerting::livewire.pages.states.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
