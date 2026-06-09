<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Monitoring', 'url' => '#'], ['label' => 'Alerting']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header title="Alerting"
            description="Pusat incident bus Nawasara — kondisi sedang firing, riwayat baru-baru ini, dan tindakan cepat."
            :count="$this->totalFiring . ' firing'" />

        {{-- Stat cards: firing per severity --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            <x-nawasara-ui::stat-card compact label="Critical"
                :value="$this->firingCounts['critical'] ?? 0" color="danger" icon="lucide-flame" />
            <x-nawasara-ui::stat-card compact label="Warning"
                :value="$this->firingCounts['warning'] ?? 0" color="warning" icon="lucide-alert-triangle" />
            <x-nawasara-ui::stat-card compact label="Acked today"
                :value="$this->acknowledgedToday" color="info" icon="lucide-check-circle" />
            <x-nawasara-ui::stat-card compact label="Resolved today"
                :value="$this->resolvedToday" color="success" icon="lucide-flag" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Firing list --}}
            <x-nawasara-ui::page.card>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">Currently firing</h3>
                    <a href="{{ route('nawasara-alerting.states') }}" wire:navigate
                        class="text-xs text-blue-600 hover:underline dark:text-blue-400">View all →</a>
                </div>

                @forelse ($this->recentFiring as $state)
                    <div class="flex items-start gap-3 py-2 border-b border-neutral-100 dark:border-neutral-700 last:border-0">
                        <div class="flex-shrink-0 mt-0.5">
                            @if ($state->severity === 'critical')
                                <span class="inline-block w-2 h-2 rounded-full bg-rose-500"></span>
                            @elseif ($state->severity === 'warning')
                                <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                            @else
                                <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-neutral-800 dark:text-neutral-100 truncate">
                                {{ $state->rule_key }}
                            </div>
                            @if ($state->target_type)
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $state->target_type }}<span class="text-neutral-400">#</span>{{ $state->target_id }}
                                </div>
                            @endif
                            <div class="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">
                                fired {{ optional($state->fired_at)->diffForHumans() ?? '-' }}
                                @if ($state->fire_count > 1)
                                    · {{ $state->fire_count }}× notified
                                @endif
                                @if ($state->isAcknowledged())
                                    · <span class="text-blue-600 dark:text-blue-400">acked</span>
                                @endif
                                @if ($state->isSilenced())
                                    · <span class="text-violet-600 dark:text-violet-400">silenced</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8">
                        <x-nawasara-ui::empty-state inline icon="lucide-circle-check"
                            title="All clear" description="Tidak ada alert yang sedang firing." />
                    </div>
                @endforelse
            </x-nawasara-ui::page.card>

            {{-- Recently resolved --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-700 dark:text-neutral-200 mb-3">Recently resolved</h3>

                @forelse ($this->recentResolved as $state)
                    <div class="flex items-start gap-3 py-2 border-b border-neutral-100 dark:border-neutral-700 last:border-0">
                        <div class="flex-shrink-0 mt-0.5">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300 truncate">
                                {{ $state->rule_key }}
                            </div>
                            @if ($state->target_type)
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $state->target_type }}<span class="text-neutral-400">#</span>{{ $state->target_id }}
                                </div>
                            @endif
                            <div class="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">
                                resolved {{ optional($state->resolved_at)->diffForHumans() ?? '-' }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8">
                        <x-nawasara-ui::empty-state inline icon="lucide-clock"
                            title="No recent resolutions" description="Belum ada alert yang resolved." />
                    </div>
                @endforelse
            </x-nawasara-ui::page.card>
        </div>
    </x-nawasara-ui::page.container>
</div>
