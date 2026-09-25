<?php

namespace App\Filament\Pages;

use App\Models\Jadwal;
use App\Models\PresensiJadwal;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Support\Hari;

class PresensiMengajar extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Presensi Mengajar';
    protected static ?string $title = 'Presensi Mengajar';
    protected static string $view = 'filament.pages.presensi-mengajar';

    public string $activeTab = 'presensi';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('guru') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('guru') ?? false;
    }

    public function getJadwalHariIni(): Collection
    {
        $guruId = auth()->user()->guru?->id;

        if (! $guruId) {
            return collect();
        }

        return Jadwal::with(['kelas', 'cabang'])
            ->where('guru_id', $guruId)
            ->where('hari', Hari::ini())
            ->where('is_active', true)
            ->orderBy('jam_mulai')
            ->get()
            ->map(function (Jadwal $jadwal) {
                $jadwal->setRelation(
                    'presensiHariIni',
                    PresensiJadwal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', today())->first(),
                );

                return $jadwal;
            });
    }

    public function checkIn(int $jadwalId, float $lat, float $lng, int $accuracy): void
    {
        $jadwal = Jadwal::with('cabang')->find($jadwalId);

        if (! $jadwal) {
            Notification::make()->danger()->title('Jadwal tidak ditemukan.')->send();
            return;
        }

        $cabang = $jadwal->cabang;

        if (is_null($cabang->latitude) || is_null($cabang->longitude)) {
            Notification::make()->danger()->title('Titik lokasi cabang belum diatur. Hubungi admin.')->send();
            return;
        }

        $distance = $cabang->distanceInMeters($lat, $lng);

        if ($distance > $cabang->radius_presensi_meter) {
            Notification::make()->danger()
                ->title('Di luar jangkauan lokasi cabang')
                ->body('Jarak kamu sekitar ' . round($distance) . ' meter (maksimal ' . $cabang->radius_presensi_meter . ' meter).')
                ->send();
            return;
        }

        $batas = Carbon::parse($jadwal->jam_mulai)->addMinutes($cabang->toleransi_keterlambatan_menit);
        $statusMasuk = now()->format('H:i:s') <= $batas->format('H:i:s') ? 'hadir' : 'terlambat';

        PresensiJadwal::updateOrCreate(
            ['jadwal_id' => $jadwal->id, 'tanggal' => today()],
            [
                'cabang_id' => $cabang->id,
                'guru_id' => $jadwal->guru_id,
                'check_in' => now(),
                'check_in_lat' => $lat,
                'check_in_lng' => $lng,
                'check_in_accuracy' => $accuracy,
                'status_masuk' => $statusMasuk,
            ],
        );

        Notification::make()->success()->title('Check In berhasil')->send();
    }

    public function checkOut(int $jadwalId, float $lat, float $lng, int $accuracy): void
    {
        $presensi = PresensiJadwal::with('jadwal')->where('jadwal_id', $jadwalId)->whereDate('tanggal', today())->first();

        if (! $presensi || ! $presensi->check_in) {
            Notification::make()->danger()->title('Kamu belum Check In untuk jadwal ini.')->send();
            return;
        }

        if ($presensi->check_out) {
            Notification::make()->warning()->title('Kamu sudah Check Out untuk jadwal ini.')->send();
            return;
        }

        $cabang = $presensi->cabang;
        $distance = $cabang->distanceInMeters($lat, $lng);

        if ($distance > $cabang->radius_presensi_meter) {
            Notification::make()->danger()
                ->title('Di luar jangkauan lokasi cabang')
                ->body('Jarak kamu sekitar ' . round($distance) . ' meter.')
                ->send();
            return;
        }

        $statusKeluar = now()->format('H:i:s') < $presensi->jadwal->jam_selesai ? 'bolos' : 'pulang';

        $presensi->update([
            'check_out' => now(),
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'check_out_accuracy' => $accuracy,
            'status_keluar' => $statusKeluar,
        ]);

        Notification::make()->success()->title('Check Out berhasil')->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PresensiJadwal::query()->where('guru_id', auth()->user()->guru?->id))
            ->columns([
                Tables\Columns\TextColumn::make('jadwal.kelas.nama_kelas')
                    ->label('Program'),
                Tables\Columns\TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in')
                    ->label('Check In')
                    ->time('H:i'),
                Tables\Columns\BadgeColumn::make('status_masuk')
                    ->label('Status Masuk')
                    ->colors([
                        'success' => 'hadir',
                        'danger' => 'terlambat',
                    ])
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('check_out')
                    ->label('Check Out')
                    ->time('H:i'),
                Tables\Columns\BadgeColumn::make('status_keluar')
                    ->label('Status Keluar')
                    ->colors([
                        'success' => 'pulang',
                        'warning' => 'bolos',
                        'danger' => 'tidak_checkout',
                    ])
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('dari')
                            ->native(false)
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('sampai')
                            ->native(false)
                            ->default(now()->endOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['dari'], fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
                            ->when($data['sampai'], fn ($q, $v) => $q->whereDate('tanggal', '<=', $v));
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }
}