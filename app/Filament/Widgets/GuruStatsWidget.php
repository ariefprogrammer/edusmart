<?php

namespace App\Filament\Widgets;

use App\Models\Jadwal;
use App\Models\PresensiJadwal;
use App\Models\PresensiSiswa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GuruStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('guru') ?? false;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $guruId = auth()->user()->guru?->id;

        $siswaTidakHadir = PresensiSiswa::query()
            ->where('guru_id', $guruId)
            ->whereIn('status', ['sakit', 'izin', 'alpa'])
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->distinct('siswa_id')
            ->count('siswa_id');

        $kehadiranSaya = PresensiJadwal::query()
            ->where('guru_id', $guruId)
            ->where('status_masuk', 'hadir')
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->count();

        $jumlahProgram = Jadwal::query()
            ->where('guru_id', $guruId)
            ->distinct('kelas_id')
            ->count('kelas_id');

        return [
            Stat::make('Siswa Tidak Hadir (Bulan Ini)', $siswaTidakHadir)
                ->icon('heroicon-o-user-minus')
                ->color('danger'),

            Stat::make('Kehadiran Saya (Bulan Ini)', $kehadiranSaya)
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Program Diampu', $jumlahProgram)
                ->icon('heroicon-o-book-open')
                ->color('warning'),
        ];
    }
}