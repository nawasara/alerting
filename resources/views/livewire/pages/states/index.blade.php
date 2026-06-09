<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Monitoring', 'url' => '#'], ['label' => 'Alert States']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header title="Alert States"
            description="Sprint 1.5 will fill this with the firing/recent table and acknowledge/resolve/silence actions." />

        <x-nawasara-ui::empty-state icon="lucide-list-checks" title="States placeholder"
            description="UI under construction — Sprint 1.5." />
    </x-nawasara-ui::page.container>
</div>
