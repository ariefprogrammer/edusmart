<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;


class Jadwal extends Model
{
    protected $table = 'jadwal';

    protected $fillable = [
        'cabang_id', 'kelas_id', 'guru_id', 'hari', 'jam_mulai', 'jam_selesai', 'is_active',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiJadwal::class);
    }

    private static function queryBeririsan(
        int|string $cabangId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
    ): Builder {
        // Normalisasi ke H:i:s supaya perbandingan konsisten ("09:00" vs "09:00:00")
        $mulai = Carbon::parse($jamMulai)->format('H:i:s');
        $selesai = Carbon::parse($jamSelesai)->format('H:i:s');

        return static::query()
            ->with(['kelas', 'guru'])
            ->where('cabang_id', $cabangId)
            ->where('hari', $hari)
            ->where('is_active', true)
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai)
            ->when($kecualiId, fn ($query) => $query->where('id', '!=', $kecualiId));
    }

    /**
     * Bentrok KERAS: guru yang sama sudah mengajar pada rentang jam yang beririsan.
     * Jam yang hanya bersinggungan (selesai 09:00, mulai 09:00) tidak dianggap bentrok.
     */
    public static function cariBentrok(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
    ): Collection {
        return static::queryBeririsan($cabangId, $hari, $jamMulai, $jamSelesai, $kecualiId)
            ->where('guru_id', $guruId)
            ->get();
    }

    public static function pesanBentrok(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
    ): ?string {
        $bentrok = static::cariBentrok($cabangId, $guruId, $kelasId, $hari, $jamMulai, $jamSelesai, $kecualiId);

        if ($bentrok->isEmpty()) {
            return null;
        }

        return $bentrok->map(function (self $jadwal) {
            $waktu = $jadwal->hari . ' ' . substr($jadwal->jam_mulai, 0, 5) . '–' . substr($jadwal->jam_selesai, 0, 5);

            return 'Guru ' . ($jadwal->guru?->nama ?? '-') . ' sudah dijadwalkan di kelas '
                . ($jadwal->kelas?->nama_kelas ?? '-') . ' pada ' . $waktu . '.';
        })->unique()->implode(' ');
    }

    /**
     * Bentrok LUNAK: kelas yang sama sudah dijadwalkan pada jam yang beririsan
     * dengan guru LAIN. Boleh dibuat, tapi harus dikonfirmasi pengguna.
     */
    public static function pesanKelasSama(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
    ): ?string {
        $ada = static::queryBeririsan($cabangId, $hari, $jamMulai, $jamSelesai, $kecualiId)
            ->where('kelas_id', $kelasId)
            ->where('guru_id', '!=', $guruId)
            ->get();

        if ($ada->isEmpty()) {
            return null;
        }

        return $ada->map(fn (self $jadwal) => 'Kelas ' . ($jadwal->kelas?->nama_kelas ?? '-')
            . ' sudah dijadwalkan pada ' . $jadwal->hari . ' '
            . substr($jadwal->jam_mulai, 0, 5) . '–' . substr($jadwal->jam_selesai, 0, 5)
            . ' dengan guru ' . ($jadwal->guru?->nama ?? '-') . '.')
            ->unique()->implode(' ');
    }
}