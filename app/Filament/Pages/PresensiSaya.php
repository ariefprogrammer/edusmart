<?php

namespace App\Filament\Pages;

use App\Models\Cabang;
use App\Models\JadwalClock;
use App\Models\PresensiKaryawan;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\Hari;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class PresensiSaya extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationLabel = 'Presensi Saya';
    protected static ?string $title = 'Presensi Saya';
    protected static string $view = 'filament.pages.presensi-saya';

    protected const MAX_ACCURACY_METER = 1000;

    public function getPresensiHariIni(): ?PresensiKaryawan
    {
        return PresensiKaryawan::where('user_id', auth()->id())
            ->whereDate('tanggal', today())
            ->first();
    }

    public function getCabang(): ?Cabang
    {
        return auth()->user()->cabang->first();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PresensiKaryawan::query()->where('user_id', auth()->id())
            )
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('l, d M Y')
                    ->sortable(),
                TextColumn::make('check_in')
                    ->label('Check In')
                    ->time('H:i')
                    ->placeholder('-'),
                TextColumn::make('status_masuk')
                    ->label('Status Masuk')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hadir' => 'success',
                        'terlambat' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('check_out')
                    ->label('Check Out')
                    ->time('H:i')
                    ->placeholder('-'),
                TextColumn::make('status_keluar')
                    ->label('Status Keluar')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pulang' => 'success',
                        'bolos' => 'danger',
                        'pulang_cepat' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal')
                            ->default(now()->startOfMonth()), // Default awal bulan ini
                        DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal')
                            ->default(now()->endOfMonth()),   // Default akhir bulan ini
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari_tanggal'] ?? null) {
                            $indicators[] = 'Mulai: ' . Carbon::parse($data['dari_tanggal'])->format('d M Y');
                        }
                        if ($data['sampai_tanggal'] ?? null) {
                            $indicators[] = 'Sampai: ' . Carbon::parse($data['sampai_tanggal'])->format('d M Y');
                        }
                        return $indicators;
                    })
            ]);
    }

    public function checkIn(float $lat, float $lng, int $accuracy, string $foto): void
    {
        $cabang = $this->getCabang();

        if (! $cabang) {
            Notification::make()->danger()->title('Kamu belum terdaftar di cabang manapun.')->send();
            return;
        }

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

        if ($accuracy > self::MAX_ACCURACY_METER) {
            Notification::make()->danger()
                ->title('Akurasi GPS terlalu rendah')
                ->body('Coba di area terbuka dan aktifkan mode GPS presisi tinggi, lalu coba lagi.')
                ->send();
            return;
        }

        $jadwal = JadwalClock::where('user_id', auth()->id())
            ->where('hari', Hari::ini())
            ->where('is_active', true)
            ->first();

        $statusMasuk = null;

        if ($jadwal) {
            $batas = Carbon::parse($jadwal->clock_in)->addMinutes($cabang->toleransi_keterlambatan_menit);
            $statusMasuk = now()->format('H:i:s') <= $batas->format('H:i:s') ? 'hadir' : 'terlambat';
        }

        PresensiKaryawan::updateOrCreate(
            ['user_id' => auth()->id(), 'tanggal' => today()],
            [
                'cabang_id' => $cabang->id,
                'check_in' => now(),
                'check_in_lat' => $lat,
                'check_in_lng' => $lng,
                'check_in_accuracy' => $accuracy,
                'check_in_foto' => $this->simpanFoto($foto, 'check-in'),
                'status_masuk' => $statusMasuk,
            ],
        );

        Notification::make()->success()->title('Check In berhasil')->send();
    }

    public function checkOut(float $lat, float $lng, int $accuracy, string $foto): void
    {
        $presensi = $this->getPresensiHariIni();

        if (! $presensi || ! $presensi->check_in) {
            Notification::make()->danger()->title('Kamu belum Check In hari ini.')->send();
            return;
        }

        if ($presensi->check_out) {
            Notification::make()->warning()->title('Kamu sudah Check Out hari ini.')->send();
            return;
        }

        $distance = $presensi->cabang->distanceInMeters($lat, $lng);

        if ($distance > $presensi->cabang->radius_presensi_meter) {
            Notification::make()->danger()
                ->title('Di luar jangkauan lokasi cabang')
                ->body('Jarak kamu sekitar ' . round($distance) . ' meter.')
                ->send();
            return;
        }

        $jadwalClock = JadwalClock::where('user_id', auth()->id())
            ->where('hari', Hari::ini())
            ->where('is_active', true)
            ->first();

        $statusKeluar = 'pulang';

        if ($jadwalClock) {
            $statusKeluar = now()->format('H:i:s') < $jadwalClock->clock_out ? 'bolos' : 'pulang';
        }

        $presensi->update([
            'check_out' => now(),
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'check_out_accuracy' => $accuracy,
            'check_out_foto' => $this->simpanFoto($foto, 'check-out'),
            'status_keluar' => $statusKeluar,
        ]);

        Notification::make()->success()->title('Check Out berhasil')->send();
    }

    protected function simpanFoto(string $base64, string $prefix): string
    {
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $filename = 'presensi/' . $prefix . '-' . auth()->id() . '-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.jpg';

        Storage::disk('public')->put($filename, base64_decode($base64));

        return $filename;
    }
}