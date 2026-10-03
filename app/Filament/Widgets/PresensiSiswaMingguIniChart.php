<?php

namespace App\Filament\Widgets;

use App\Models\PresensiSiswa;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class PresensiSiswaMingguIniChart extends ChartWidget
{
    protected static ?string $heading = 'Presensi Siswa Minggu Ini';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin_cabang') ?? false;
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $cabangId = auth()->user()->cabang->first()?->id;

        $counts = PresensiSiswa::query()
            ->where('cabang_id', $cabangId)
            ->whereBetween('tanggal', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = ['hadir', 'izin', 'sakit', 'alpa'];

        return [
            'datasets' => [
                [
                    'data' => array_map(fn ($status) => $counts[$status] ?? 0, $labels),
                    'backgroundColor' => [
                        '#22c55e',
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444',
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