<?php

namespace App\Filament\Pages;

use App\Models\Kelas;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\JadwalPengganti;
use Illuminate\Support\Facades\DB;

class ProgramSaya extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Program Saya';
    protected static ?string $title = 'Program Saya';
    protected static string $view = 'filament.pages.program-saya';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('guru') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('guru') ?? false;
    }

    public function getProgramList(): Collection
    {
        $guruId = auth()->user()->guru?->id;

        if (! $guruId) {
            return collect();
        }

        $programs = Kelas::query()
            ->where('is_active', true)
            ->whereHas('jadwal', fn (Builder $q) => $q->where('guru_id', $guruId)->where('is_active', true))
            ->with([
                'cabang',
                // Hanya jadwal aktif milik guru yang login
                'jadwal' => fn (HasMany $q) => $q->where('guru_id', $guruId)->where('is_active', true),
            ])
            ->get();

        $jadwalIds = $programs->flatMap(fn (Kelas $kelas) => $kelas->jadwal->pluck('id'))->all();

        // Siswa aktif per jam
        $perJam = DB::table('jadwal_siswa')
            ->join('kelas_siswa', 'kelas_siswa.id', '=', 'jadwal_siswa.kelas_siswa_id')
            ->join('siswa', 'siswa.id', '=', 'jadwal_siswa.siswa_id')
            ->whereIn('jadwal_siswa.jadwal_id', $jadwalIds)
            ->where('kelas_siswa.is_active', true)
            ->whereNull('siswa.deleted_at')
            ->groupBy('jadwal_siswa.jadwal_id')
            ->selectRaw('jadwal_siswa.jadwal_id, COUNT(*) as total')
            ->pluck('total', 'jadwal_id');

        // Siswa aktif di seluruh jam milik guru ini, per program (tanpa dihitung ganda)
        $perKelas = DB::table('jadwal_siswa')
            ->join('jadwal', 'jadwal.id', '=', 'jadwal_siswa.jadwal_id')
            ->join('kelas_siswa', 'kelas_siswa.id', '=', 'jadwal_siswa.kelas_siswa_id')
            ->join('siswa', 'siswa.id', '=', 'jadwal_siswa.siswa_id')
            ->whereIn('jadwal_siswa.jadwal_id', $jadwalIds)
            ->where('kelas_siswa.is_active', true)
            ->whereNull('siswa.deleted_at')
            ->groupBy('jadwal.kelas_id')
            ->selectRaw('jadwal.kelas_id, COUNT(DISTINCT jadwal_siswa.siswa_id) as total')
            ->pluck('total', 'kelas_id');

        $hariUrut = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        foreach ($programs as $kelas) {
            $min = (int) $kelas->kuota_minimal;

            $kelas->jadwal->each(function ($jadwal) use ($perJam, $min) {
                $jumlah = (int) $perJam->get($jadwal->id, 0);

                $jadwal->setAttribute('siswa_aktif_count', $jumlah);
                // Jam yang siswanya belum mencapai kuota minimal tidak bisa dimasuki
                $jadwal->setAttribute('kuota_kurang', $min > 0 && $jumlah < $min);
            });

            $kelas->setRelation(
                'jadwal',
                $kelas->jadwal
                    ->sortBy(fn ($jadwal) => [array_search($jadwal->hari, $hariUrut), $jadwal->jam_mulai])
                    ->values()
            );

            $kelas->setAttribute('siswa_aktif_count', (int) $perKelas->get($kelas->id, 0));
        }

        return $programs;
    }

    public function getJadwalPenggantiList(): Collection
    {
        $guruId = auth()->user()->guru?->id;

        if (! $guruId) {
            return collect();
        }

        return JadwalPengganti::query()
            ->with(['kelas', 'cabang'])
            ->withCount('siswa')
            ->where('guru_id', $guruId)
            ->where('status', JadwalPengganti::STATUS_TERJADWAL)
            ->whereDate('tanggal_pengganti', '>=', today()->toDateString())
            ->orderBy('tanggal_pengganti')
            ->orderBy('jam_mulai')
            ->get();
    }
}