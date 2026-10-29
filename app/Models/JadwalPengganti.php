<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JadwalPengganti extends Model
{
    protected $table = 'jadwal_pengganti';

    public const STATUS_TERJADWAL = 'terjadwal';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'cabang_id', 'kelas_id', 'jadwal_id', 'guru_id',
        'tanggal_seharusnya', 'tanggal_pengganti', 'jam_mulai', 'jam_selesai',
        'alasan', 'status', 'selesai_pada', 'dibuat_oleh',
    ];

    protected $casts = [
        'tanggal_seharusnya' => 'date',
        'tanggal_pengganti' => 'date',
        'selesai_pada' => 'datetime',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'jadwal_pengganti_siswa', 'jadwal_pengganti_id', 'siswa_id')
            ->withTimestamps();
    }

    /** Nama hari (sesuai enum jadwal.hari) dari sebuah tanggal, tidak bergantung locale. */
    public static function namaHari(Carbon|string $tanggal): string
    {
        return [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ][Carbon::parse($tanggal)->dayOfWeekIso];
    }

    /** Jadwal pengganti lain (status terjadwal) yang bentrok di tanggal yang sama. */
    public static function cariBentrokPengganti(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $tanggalPengganti,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
    ): Collection {
        $mulai = Carbon::parse($jamMulai)->format('H:i:s');
        $selesai = Carbon::parse($jamSelesai)->format('H:i:s');

        return static::query()
            ->with(['kelas', 'guru'])
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_pengganti', Carbon::parse($tanggalPengganti)->toDateString())
            ->where('status', self::STATUS_TERJADWAL)
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai)
            ->where(fn ($query) => $query
                ->where('guru_id', $guruId)
                ->orWhere('kelas_id', $kelasId))
            ->when($kecualiId, fn ($query) => $query->where('id', '!=', $kecualiId))
            ->get();
    }

    /**
     * Pesan bentrok untuk jadwal pengganti: dicek terhadap jadwal reguler aktif
     * (lewat Jadwal::pesanBentrok pada hari dari tanggal_pengganti) dan terhadap
     * jadwal pengganti lain. Mengembalikan null jika tidak ada bentrok.
     */
    public static function pesanBentrok(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $tanggalPengganti,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
    ): ?string {
        $pesan = [];

        $reguler = Jadwal::pesanBentrok(
            $cabangId, $guruId, $kelasId,
            static::namaHari($tanggalPengganti),
            $jamMulai, $jamSelesai,
        );

        if ($reguler) {
            $pesan[] = $reguler;
        }

        $pengganti = static::cariBentrokPengganti(
            $cabangId, $guruId, $kelasId, $tanggalPengganti, $jamMulai, $jamSelesai, $kecualiId,
        );

        foreach ($pengganti as $item) {
            $waktu = $item->tanggal_pengganti->format('d-m-Y') . ' '
                . substr($item->jam_mulai, 0, 5) . '–' . substr($item->jam_selesai, 0, 5);

            $pesan[] = (int) $item->guru_id === (int) $guruId
                ? 'Guru ' . ($item->guru?->nama ?? '-') . ' sudah memiliki jadwal pengganti di kelas '
                    . ($item->kelas?->nama_kelas ?? '-') . ' pada ' . $waktu . '.'
                : 'Kelas ' . ($item->kelas?->nama_kelas ?? '-') . ' sudah memiliki jadwal pengganti pada '
                    . $waktu . ' (guru ' . ($item->guru?->nama ?? '-') . ').';
        }

        return $pesan ? implode(' ', array_unique($pesan)) : null;
    }

    /** Status untuk tampilan: jadwal terjadwal yang tanggalnya sudah lewat disebut "terlewat". */
    public function statusTampil(): string
    {
        return $this->status === self::STATUS_TERJADWAL && $this->tanggal_pengganti->lt(today())
            ? 'terlewat'
            : $this->status;
    }

    /**
     * Validasi daftar siswa. Mengembalikan pesan error, atau null jika semuanya valid:
     * (1) siswa baru harus aktif (siswa dan pivot) di kelas ini;
     * (2) siswa tidak boleh ada di dua jadwal pengganti "terjadwal" untuk kelas dan
     *     tanggal seharusnya yang sama.
     * $sudahTerdaftarIds: siswa yang memang sudah ada di jadwal ini (saat edit) tidak dicek ulang pada (1).
     */
    public static function pesanSiswaBermasalah(
        int|string $kelasId,
        string $tanggalSeharusnya,
        array $siswaIds,
        int|string|null $kecualiId = null,
        array $sudahTerdaftarIds = [],
    ): ?string {
        $siswaIds = array_map('intval', $siswaIds);

        if ($siswaIds === []) {
            return null;
        }

        $pesan = [];

        $perluDicek = array_diff($siswaIds, array_map('intval', $sudahTerdaftarIds));

        if ($perluDicek) {
            $valid = Siswa::query()
                ->where('is_active', true)
                ->whereHas('kelasList', fn ($query) => $query
                    ->where('kelas.id', $kelasId)
                    ->where('kelas_siswa.is_active', true))
                ->whereIn('id', $perluDicek)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $tidakValid = array_diff($perluDicek, $valid);

            if ($tidakValid) {
                $pesan[] = 'Bukan siswa aktif di kelas ini: '
                    . Siswa::withTrashed()->whereIn('id', $tidakValid)->pluck('nama')->implode(', ') . '.';
            }
        }

        $duplikat = DB::table('jadwal_pengganti_siswa as ps')
            ->join('jadwal_pengganti as jp', 'jp.id', '=', 'ps.jadwal_pengganti_id')
            ->where('jp.kelas_id', $kelasId)
            ->whereDate('jp.tanggal_seharusnya', Carbon::parse($tanggalSeharusnya)->toDateString())
            ->where('jp.status', self::STATUS_TERJADWAL)
            ->whereIn('ps.siswa_id', $siswaIds)
            ->when($kecualiId, fn ($query) => $query->where('jp.id', '!=', $kecualiId))
            ->pluck('ps.siswa_id')
            ->all();

        if ($duplikat) {
            $pesan[] = 'Sudah terdaftar di jadwal pengganti lain untuk tanggal seharusnya yang sama: '
                . Siswa::withTrashed()->whereIn('id', $duplikat)->pluck('nama')->implode(', ') . '.';
        }

        return $pesan ? implode(' ', $pesan) : null;
    }
}