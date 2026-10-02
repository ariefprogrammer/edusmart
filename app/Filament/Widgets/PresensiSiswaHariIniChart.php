<?php

namespace App\Filament\Widgets;

use App\Models\PresensiSiswa;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class PresensiSiswaHariIniChart extends ChartWidget
{
    protected static ?string $heading = 'Presensi Siswa Minggu Ini';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $counts = PresensiSiswa::query()
            ->whereBetween('tanggal', [
                now()->startOfWeek()->toDateString(), 
                now()->endOfWeek()->toDateString()
            ])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = ['hadir', 'izin', 'sakit', 'alpa'];

        return [
            'datasets' => [
                [
                    'data' => array_map(fn ($status) => $counts[$status] ?? 0, $labels),
                    'backgroundColor' => [
                        '#22c55e', // Hadir (Hijau)
                        '#3b82f6', // Izin (Biru)
                        '#f59e0b', // Sakit (Kuning/Oranye)
                        '#ef4444', // Alpa (Merah)
                    ],
                ],
            ],
            'labels' => ['Hadir', 'Izin', 'Sakit', 'Alpa'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                    },
                },
            }
        JS);
    }
}