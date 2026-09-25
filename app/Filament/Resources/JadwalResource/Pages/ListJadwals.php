<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListJadwals extends ListRecords
{
    protected static string $resource = JadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    #[On('jadwal-kelas-updated')]
    public function refreshTable(): void
    {
        // Kosong secara sengaja — cukup memicu re-render halaman List
        // (termasuk tabelnya) saat event diterima dari JadwalKelasDetail.
    }
}