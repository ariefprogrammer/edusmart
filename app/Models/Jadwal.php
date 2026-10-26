<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;


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

    /**
     * Cari jadwal AKTIF yang bentrok dengan jadwal yang akan dibuat / diubah.
     *
     * Bentrok jika: cabang sama, hari sama, rentang jam beririsan, dan
     * (guru sama ATAU kelas sama). Jam yang hanya bersinggungan
     * (09:00 selesai, 09:00 mulai) TIDAK dianggap bentrok.
     * Jadwal nonaktif (is_active = false) tidak dihitung.
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
            ->where(fn ($query) => $query
                ->where('guru_id', $guruId)
                ->orWhere('kelas_id', $kelasId))
            ->when($kecualiId, fn ($query) => $query->where('id', '!=', $kecualiId))
            ->get();
    }

    /**
     * Versi pesan error dari cariBentrok(). Mengembalikan null jika tidak ada bentrok.
     */
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

        return $bentrok->map(function (self $jadwal) use ($guruId) {
            $waktu = $jadwal->hari . ' ' . substr($jadwal->jam_mulai, 0, 5) . '–' . substr($jadwal->jam_selesai, 0, 5);

            if ((int) $jadwal->guru_id === (int) $guruId) {
                return 'Guru ' . ($jadwal->guru?->nama ?? '-') . ' sudah dijadwalkan di kelas '
                    . ($jadwal->kelas?->nama_kelas ?? '-') . ' pada ' . $waktu . '.';
            }

            return 'Kelas ' . ($jadwal->kelas?->nama_kelas ?? '-') . ' sudah memiliki jadwal pada '
                . $waktu . ' (guru ' . ($jadwal->guru?->nama ?? '-') . ').';
        })->unique()->implode(' ');
    }
}