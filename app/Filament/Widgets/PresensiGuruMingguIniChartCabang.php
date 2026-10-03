<?php

namespace App\Filament\Widgets;

use App\Models\PresensiJadwal;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class PresensiGuruMingguIniChartCabang extends ChartWidget
{
    protected static ?string $heading = 'Presensi Guru Minggu Ini';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin_cabang') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $cabangId = auth()->user()->cabang->first()?->id;

        $range = [now()->startOfWeek(), now()->endOfWeek()];

        $masuk = PresensiJadwal::query()
            ->where('cabang_id', $cabangId)
            ->whereBetween('tanggal', $range)
            ->selectRaw('status_masuk, count(*) as total')
            ->groupBy('status_masuk')
            ->pluck('total', 'status_masuk');

        $keluar = PresensiJadwal::query()
            ->where('cabang_id', $cabangId)
            ->whereBetween('tanggal', $range)
            ->selectRaw('status_keluar, count(*) as total')
            ->groupBy('status_keluar')
            ->pluck('total', 'status_keluar');

        return [
            'datasets' => [
                [
                    'data' => [
                        $masuk['hadir'] ?? 0,
                        $masuk['terlambat'] ?? 0,
                        $keluar['pulang'] ?? 0,
                        $keluar['bolos'] ?? 0,
                        $keluar['tidak_checkout'] ?? 0,
                    ],
                    'backgroundColor' => [
                        '#22c55e',
                        '#ef4444',
                        '#22c55e',
                        '#ef4444',
                        '#f59e0b',
                    ],
                ],
            ],
            'labels' => ['Hadir', 'Terlambat', 'Pulang', 'Bolos', 'Tidak Checkout'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.3,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            generateLabels: (chart) => {
                                const data = chart.data;
                                return data.labels.map((label, i) => ({
                                    text: label,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    index: i,
                                }));
                            },
                        },
                    },
                },
                scales: {
                    x: { display: false },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            }
        JS);
    }
}