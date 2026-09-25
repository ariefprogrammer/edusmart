<?php

namespace App\Filament\Resources\JadwalClockResource\Pages;

use App\Filament\Resources\JadwalClockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListJadwalClocks extends ListRecords
{
    protected static string $resource = JadwalClockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    #[On('jadwal-updated')]
    public function refreshTable(): void
    {
        // Method ini sengaja dibiarkan kosong — keberadaannya cukup
        // untuk memicu Livewire me-render ulang komponen List ini
        // (termasuk tabelnya) saat event 'jadwal-updated' diterima
        // dari komponen JadwalGuruDetail di dalam modal.
    }
}