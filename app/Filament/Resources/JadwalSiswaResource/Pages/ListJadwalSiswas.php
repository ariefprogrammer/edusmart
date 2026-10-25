<?php

namespace App\Filament\Resources\JadwalSiswaResource\Pages;

use App\Exports\JadwalSiswaExport;
use App\Filament\Resources\JadwalSiswaResource;
use App\Models\Cabang;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListJadwalSiswas extends ListRecords
{
    protected static string $resource = JadwalSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),

            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->extraAttributes([
                    'style' => 'background-color: #2563eb; color: #ffffff; border-color: #2563eb;',
                ])
                ->action(function () {
                    // Query tabel: sudah memuat pembatasan cabang, filter, dan pencarian
                    $siswaList = $this->getFilteredSortedTableQuery()
                        ->with(['cabang', 'kelasList.jadwal.guru'])
                        ->get();

                    if (! $siswaList->contains(fn ($siswa) => $siswa->kelasList->isNotEmpty())) {
                        Notification::make()->warning()->title('Tidak ada data untuk diexport')->send();

                        return;
                    }

                    $spreadsheet = (new JadwalSiswaExport())->build($siswaList, $this->metaExport());

                    return response()->streamDownload(function () use ($spreadsheet) {
                        (new Xlsx($spreadsheet))->save('php://output');
                    }, 'jadwal-siswa-' . now()->format('Y-m-d_His') . '.xlsx');
                }),
        ];
    }

    protected function metaExport(): array
    {
        $cabangId = $this->tableFilters['cabang_id']['value'] ?? null;

        if ($cabangId) {
            $cabang = Cabang::find($cabangId)?->nama_cabang ?? '-';
        } elseif (JadwalSiswaResource::isCabangRestricted()) {
            $cabang = Cabang::whereIn('id', JadwalSiswaResource::getScopedCabangIds())
                ->orderBy('nama_cabang')
                ->pluck('nama_cabang')
                ->implode(', ');
        } else {
            $cabang = 'Semua Cabang';
        }

        return [
            'cabang' => $cabang,
            'pencarian' => filled($this->tableSearch) ? $this->tableSearch : '-',
        ];
    }
}