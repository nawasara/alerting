<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Monitoring', 'url' => '#'], ['label' => 'Alert States']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header title="Alert States"
            description="Daftar lengkap alert (firing + resolved). Klik baris untuk lihat detail, acknowledge, atau silence."
            :count="$this->rows->total() . ' total'" />

        {{-- Filter panel — single WHM-style panel --}}
        <div class="space-y-2 mb-4">
            <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-nawasara-ui::filter-panel label="Filter" :state="[
                            'statusFilter' => $statusFilter,
                            'severityFilter' => $severityFilter,
                        ]" :labels="[
                            'statusFilter' => ['firing' => 'Firing', 'ok' => 'Resolved'],
                            'severityFilter' => ['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info'],
                        ]"
                        :dimensions="[
                            'statusFilter' => 'Status',
                            'severityFilter' => 'Severity',
                        ]">
                        <x-nawasara-ui::filter-group label="Status" model="statusFilter"
                            :items="['firing' => 'Firing', 'ok' => 'Resolved']"
                            icon="lucide-circle" />
                        <x-nawasara-ui::filter-group label="Severity" model="severityFilter"
                            :items="['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info']"
                            icon="lucide-flame" />
                    </x-nawasara-ui::filter-panel>
                </div>

                <x-nawasara-ui::search-input model="search" placeholder="Cari rule, target type, atau target id..." />
            </div>

            <div wire:ignore data-filter-chips></div>
        </div>

        {{-- Table --}}
        <x-nawasara-ui::table stickyLast :headers="['Severity', 'Rule', 'Target', 'Status', 'Fired', 'Count', '']">
            <x-slot:table>
                @forelse ($this->rows as $row)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40 cursor-pointer"
                        wire:click="openDetail({{ $row->id }})">
                        <td class="px-4 py-2.5">
                            <x-nawasara-ui::badge
                                :color="$row->severity === 'critical' ? 'danger' : ($row->severity === 'warning' ? 'warning' : 'info')">
                                {{ ucfirst($row->severity) }}
                            </x-nawasara-ui::badge>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="text-sm font-medium text-neutral-800 dark:text-neutral-100">{{ $row->rule_key }}</div>
                            @if ($row->isAcknowledged())
                                <div class="text-xs text-blue-600 dark:text-blue-400">acknowledged</div>
                            @endif
                            @if ($row->isSilenced())
                                <div class="text-xs text-violet-600 dark:text-violet-400">
                                    silenced until {{ $row->silenced_until->diffForHumans() }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">
                            @if ($row->target_type)
                                <code class="text-xs">{{ $row->target_type }}#{{ $row->target_id }}</code>
                            @else
                                <span class="text-neutral-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($row->status === 'firing')
                                <x-nawasara-ui::badge color="danger">Firing</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="success">Resolved</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                            {{ optional($row->fired_at)->diffForHumans() ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200 text-center">
                            {{ $row->fire_count }}
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <x-nawasara-ui::icon-button icon="eye" tooltip="Detail" placement="left"
                                wire:click.stop="openDetail({{ $row->id }})" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6">
                            <x-nawasara-ui::empty-state inline icon="lucide-bell-off" title="Tidak ada alert"
                                description="Belum ada state yang cocok dengan filter ini." />
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">
            {{ $this->rows->links() }}
        </div>

        {{-- Detail modal --}}
        <x-nawasara-ui::modal id="alerting-state-detail" title="Alert detail" maxWidth="2xl">
            @if ($this->detail)
                @php $d = $this->detail; @endphp
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-sm text-neutral-500 dark:text-neutral-400 mb-1">Rule</div>
                            <div class="text-base font-semibold text-neutral-800 dark:text-neutral-100 truncate">
                                {{ $d->rule_key }}
                            </div>
                        </div>
                        <x-nawasara-ui::badge
                            :color="$d->severity === 'critical' ? 'danger' : ($d->severity === 'warning' ? 'warning' : 'info')">
                            {{ ucfirst($d->severity) }} · {{ ucfirst($d->status) }}
                        </x-nawasara-ui::badge>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">Target</div>
                            <div class="text-neutral-800 dark:text-neutral-100">
                                @if ($d->target_type)
                                    <code class="text-xs">{{ $d->target_type }}#{{ $d->target_id }}</code>
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">Fire count</div>
                            <div class="text-neutral-800 dark:text-neutral-100">{{ $d->fire_count }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">First fired</div>
                            <div class="text-neutral-800 dark:text-neutral-100">
                                {{ optional($d->fired_at)->toDateTimeString() ?? '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">Last notified</div>
                            <div class="text-neutral-800 dark:text-neutral-100">
                                {{ optional($d->last_notified_at)->toDateTimeString() ?? '-' }}
                            </div>
                        </div>
                        @if ($d->resolved_at)
                            <div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">Resolved at</div>
                                <div class="text-emerald-700 dark:text-emerald-400">
                                    {{ $d->resolved_at->toDateTimeString() }}
                                </div>
                            </div>
                        @endif
                        @if ($d->isAcknowledged())
                            <div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">Acknowledged</div>
                                <div class="text-blue-700 dark:text-blue-400">
                                    {{ $d->acknowledged_at->toDateTimeString() }}
                                    @if ($d->acknowledgedBy)
                                        by {{ $d->acknowledgedBy->name }}
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if ($d->isSilenced())
                            <div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">Silenced until</div>
                                <div class="text-violet-700 dark:text-violet-400">
                                    {{ $d->silenced_until->toDateTimeString() }}
                                    @if ($d->silencedBy)
                                        by {{ $d->silencedBy->name }}
                                    @endif
                                    @if ($d->silenced_reason)
                                        <div class="text-xs italic text-neutral-500 dark:text-neutral-400 mt-1">
                                            {{ $d->silenced_reason }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    @if (! empty($d->context))
                        <div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Context</div>
                            <pre
                                class="text-xs bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded p-3 overflow-x-auto text-neutral-800 dark:text-neutral-200">{{ json_encode($d->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif

                    @if ($d->status === 'firing')
                        <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-2">Silence for</div>
                            <div class="flex items-center gap-2">
                                <x-nawasara-ui::form.input type="number" wire:model="silenceMinutes" />
                                <span class="text-sm text-neutral-600 dark:text-neutral-300">minutes</span>
                                <x-nawasara-ui::form.input type="text" wire:model="silenceReason"
                                    placeholder="Reason (optional)" />
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <x-slot:footer>
                @if ($this->detail && $this->detail->status === 'firing')
                    @can('alerting.acknowledge')
                        @if (! $this->detail->isAcknowledged())
                            <x-nawasara-ui::button color="info"
                                wire:click="acknowledgeAction({{ $this->detail->id }})">
                                Acknowledge
                            </x-nawasara-ui::button>
                        @endif
                    @endcan

                    @can('alerting.silence')
                        <x-nawasara-ui::button color="warning"
                            wire:click="silenceAction({{ $this->detail->id }})">
                            Silence
                        </x-nawasara-ui::button>
                    @endcan

                    @can('alerting.resolve')
                        <x-nawasara-ui::button color="success"
                            wire:click="resolveAction({{ $this->detail->id }})">
                            Force resolve
                        </x-nawasara-ui::button>
                    @endcan
                @endif

                <x-nawasara-ui::button color="neutral" variant="outline"
                    x-on:click="$dispatch('close-modal', 'alerting-state-detail')">
                    Close
                </x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>
