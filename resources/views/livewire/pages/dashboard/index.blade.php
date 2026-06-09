<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Monitoring', 'url' => '#'], ['label' => 'Alerting']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header title="Alerting"
            description="Sprint 1.5 will fill this dashboard with firing counts, recent activity, and a 7-day trend." />

        <x-nawasara-ui::empty-state icon="lucide-bell-ring" title="Dashboard placeholder"
            description="UI under construction — Sprint 1.5." />
    </x-nawasara-ui::page.container>
</div>
