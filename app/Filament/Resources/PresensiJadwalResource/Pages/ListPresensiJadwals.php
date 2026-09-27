<?php

namespace App\Filament\Resources\PresensiJadwalResource\Pages;

use App\Filament\Resources\PresensiJadwalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListPresensiJadwals extends ListRecords
{
    protected static string $resource = PresensiJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    #[On('presensi-jadwal-updated')]
    public function refreshTable(): void
    {
        // Kosong secara sengaja — cukup memicu re-render halaman List.
    }
}