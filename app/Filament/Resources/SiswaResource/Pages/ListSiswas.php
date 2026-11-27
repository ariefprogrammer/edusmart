<?php

namespace App\Filament\Resources\SiswaResource\Pages;

use App\Filament\Resources\SiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Exports\SiswaFormulirExport;
use Filament\Notifications\Notification;

class ListSiswas extends ListRecords
{
    protected static string $resource = SiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->extraAttributes([
                    'style' => 'background-color: #2563eb; color: #ffffff; border-color: #2563eb;',
                ])
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $siswaList = $this->getFilteredSortedTableQuery()->with('keluarga')->get();

                    if ($siswaList->isEmpty()) {
                        Notification::make()->warning()->title('Tidak ada data untuk diexport')->send();

                        return;
                    }

                    return SiswaFormulirExport::download($siswaList);
                }),
        ];
    }
}
