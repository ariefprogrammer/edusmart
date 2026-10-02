<x-filament-panels::page>
    <x-filament::section>
        <p class="text-lg font-medium">
            Hallo, selamat datang {{ $roleUser }}.
        </p>
    </x-filament::section>

    <x-filament-widgets::widgets
        :widgets="$this->getVisibleWidgets()"
        :columns="$this->getColumns()"
    />
</x-filament-panels::page>