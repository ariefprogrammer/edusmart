<?php

namespace App\Filament\Resources\ReportSiswaResource\Pages;

use App\Filament\Resources\ReportSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReportSiswa extends ViewRecord
{
    protected static string $resource = ReportSiswaResource::class;

    public function getTitle(): string
    {
        return 'Report '.$this->getRecord()->siswa_nama;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('unduhPdf')
                ->label('Unduh PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => ReportSiswaResource::pdfResponse($this->getRecord())),

            Actions\Action::make('buatUlang')
                ->label('Buat Ulang')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Report baru dibuat dengan filter yang sama memakai data terbaru. Report ini tetap disimpan.')
                ->visible(fn () => ReportSiswaResource::canCreate())
                ->action(function () {
                    $baru = ReportSiswaResource::buatUlang($this->getRecord());

                    if ($baru) {
                        $this->redirect(ReportSiswaResource::getUrl('view', ['record' => $baru]));
                    }
                }),

            Actions\DeleteAction::make()
                ->successRedirectUrl(ReportSiswaResource::getUrl('index')),
        ];
    }
}
