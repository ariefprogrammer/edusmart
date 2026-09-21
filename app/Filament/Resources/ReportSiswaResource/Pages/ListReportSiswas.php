<?php

namespace App\Filament\Resources\ReportSiswaResource\Pages;

use App\Filament\Resources\ReportSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportSiswas extends ListRecords
{
    protected static string $resource = ReportSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('buatReport')
                ->label('Buat Report')
                ->icon('heroicon-o-plus')
                ->visible(fn () => ReportSiswaResource::canCreate())
                ->modalHeading('Buat Report Siswa')
                ->modalDescription('Data dibaca sekali dari sumbernya lalu disimpan sebagai snapshot. Perubahan data setelah ini tidak memengaruhi report.')
                ->modalSubmitActionLabel('Buat Report')
                ->modalWidth('3xl')
                ->form(ReportSiswaResource::formSchema())
                ->action(function (array $data, Actions\Action $action): void {
                    $hasil = ReportSiswaResource::buatDariForm($data);

                    if ($hasil === null) {
                        $action->halt(); // modal tetap terbuka supaya filter bisa diperbaiki
                    }

                    if (filled($hasil['url'])) {
                        $action->redirect($hasil['url']);
                    }
                }),
        ];
    }
}