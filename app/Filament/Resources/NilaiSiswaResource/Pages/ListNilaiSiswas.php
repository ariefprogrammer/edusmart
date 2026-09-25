<?php

namespace App\Filament\Resources\NilaiSiswaResource\Pages;

use App\Filament\Resources\NilaiSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListNilaiSiswas extends ListRecords
{
    protected static string $resource = NilaiSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    #[On('nilai-siswa-updated')]
    public function refreshTable(): void
    {
        // Kosong secara sengaja — cukup memicu re-render halaman List
        // saat event diterima dari NilaiSiswaDetail.
    }
}