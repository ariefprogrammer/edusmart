<?php

namespace App\Filament\Pages;

use App\Models\Kelas;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\JadwalPengganti;

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

        return Kelas::query()
            ->where('is_active', true)
            ->whereHas('jadwal', fn (Builder $q) => $q->where('guru_id', $guruId))
            ->with([
                'cabang',
                'jadwal' => fn (HasMany $q) => $q->where('guru_id', $guruId),
                'siswa' => fn (BelongsToMany $q) => $q->orderBy('nama'),
            ])
            ->withCount(['siswa as siswa_aktif_count' => fn (Builder $q) => $q->where('kelas_siswa.is_active', true)])
            ->get();
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