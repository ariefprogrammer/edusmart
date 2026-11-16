<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\PenjadwalanSiswa;

class JadwalPengganti extends Model
{
    protected $table = 'jadwal_pengganti';

    public const STATUS_TERJADWAL = 'terjadwal';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';
    public const TEKS_PENANDA = 'dilaksanakan pertemuan pengganti';

    protected $fillable = [
        'cabang_id', 'kelas_id', 'jadwal_id', 'jadwal_tujuan_id', 'guru_id',
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
            ->withPivot('presensi_disimpan_pada')
            ->withTimestamps();
    }

    public function jadwalTujuan(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_tujuan_id');
    }

    /** Nama hari (sesuai enum jadwal.hari) dari sebuah tanggal, tidak bergantung locale. */
    public static function namaHari(Carbon|string $tanggal): string
    {
        return [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ][Carbon::parse($tanggal)->dayOfWeekIso];
    }

    /** Jadwal pengganti lain (status terjadwal) milik guru yang sama yang bentrok di tanggal yang sama. */
    public static function cariBentrokPengganti(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $tanggalPengganti,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
        int|string|null $jadwalTujuanId = null,
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
            // Hanya guru yang sama yang dianggap bentrok; program yang sama boleh punya sesi paralel
            ->where('guru_id', $guruId)
            // Pengganti lain yang menuju jadwal tujuan yang sama berbagi satu sesi, bukan bentrok
            ->when($jadwalTujuanId, fn ($query) => $query->where(fn ($q) => $q
                ->whereNull('jadwal_tujuan_id')
                ->orWhere('jadwal_tujuan_id', '!=', $jadwalTujuanId)))
            ->when($kecualiId, fn ($query) => $query->where('id', '!=', $kecualiId))
            ->get();
    }

    /**
     * Pesan bentrok untuk jadwal pengganti: dicek terhadap jadwal reguler aktif
     * (lewat Jadwal::pesanBentrok pada hari dari tanggal_pengganti) dan terhadap
     * jadwal pengganti lain. Mengembalikan null jika tidak ada bentrok.
     * $jadwalTujuanId dikecualikan dari cek reguler karena guru dan jamnya memang
     * sama dengan jadwal pengganti ini.
     */
    public static function pesanBentrok(
        int|string $cabangId,
        int|string $guruId,
        int|string $kelasId,
        string $tanggalPengganti,
        string $jamMulai,
        string $jamSelesai,
        int|string|null $kecualiId = null,
        int|string|null $jadwalTujuanId = null,
    ): ?string {
        $pesan = [];

        $reguler = Jadwal::pesanBentrok(
            $cabangId, $guruId, $kelasId,
            static::namaHari($tanggalPengganti),
            $jamMulai, $jamSelesai,
            $jadwalTujuanId,
        );

        if ($reguler) {
            $pesan[] = $reguler;
        }

        $pengganti = static::cariBentrokPengganti(
            $cabangId, $guruId, $kelasId, $tanggalPengganti, $jamMulai, $jamSelesai, $kecualiId, $jadwalTujuanId,
        );

        foreach ($pengganti as $item) {
            $waktu = $item->tanggal_pengganti->format('d-m-Y') . ' '
                . substr($item->jam_mulai, 0, 5) . '–' . substr($item->jam_selesai, 0, 5);

            $pesan[] = 'Guru ' . ($item->guru?->nama ?? '-') . ' sudah memiliki jadwal pengganti di kelas '
                . ($item->kelas?->nama_kelas ?? '-') . ' pada ' . $waktu . '.';
        }

        return $pesan ? implode(' ', array_unique($pesan)) : null;
    }

    /**
     * Cek apakah kuota jadwal tujuan pada tanggal pengganti cukup untuk siswa ini.
     * Isi jadwal tujuan = peserta tetapnya (siswa aktif) + siswa dari jadwal pengganti lain
     * (status terjadwal) yang menuju jadwal dan tanggal yang sama. Siswa yang sudah termasuk
     * di dalamnya tidak dihitung sebagai tambahan. Mengembalikan pesan jika tidak cukup, atau null.
     */
    public static function pesanKuotaTujuan(
        int|string $jadwalTujuanId,
        string $tanggalPengganti,
        array $siswaIds,
        int|string|null $kecualiId = null,
    ): ?string {
        $tujuan = Jadwal::with(['kelas', 'guru'])->find($jadwalTujuanId);
        $siswaIds = array_map('intval', $siswaIds);

        if (! $tujuan || $siswaIds === []) {
            return null;
        }

        $maks = $tujuan->kelas?->kuota_maksimal;

        // Kuota kosong = tanpa batas
        if ($maks === null) {
            return null;
        }

        $tanggal = Carbon::parse($tanggalPengganti)->toDateString();

        // Peserta tetap jadwal tujuan: siswa aktif di program, tidak dihapus
        $tetap = DB::table('jadwal_siswa')
            ->join('kelas_siswa', 'kelas_siswa.id', '=', 'jadwal_siswa.kelas_siswa_id')
            ->join('siswa', 'siswa.id', '=', 'jadwal_siswa.siswa_id')
            ->where('jadwal_siswa.jadwal_id', $tujuan->getKey())
            ->where('kelas_siswa.is_active', true)
            ->whereNull('siswa.deleted_at')
            ->pluck('jadwal_siswa.siswa_id');

        // Siswa dari jadwal pengganti lain yang menuju jadwal dan tanggal yang sama
        $titipan = DB::table('jadwal_pengganti_siswa as ps')
            ->join('jadwal_pengganti as jp', 'jp.id', '=', 'ps.jadwal_pengganti_id')
            ->where('jp.jadwal_tujuan_id', $tujuan->getKey())
            ->whereDate('jp.tanggal_pengganti', $tanggal)
            ->where('jp.status', self::STATUS_TERJADWAL)
            ->when($kecualiId, fn ($query) => $query->where('jp.id', '!=', $kecualiId))
            ->pluck('ps.siswa_id');

        $terisi = $tetap->merge($titipan)->map(fn ($id) => (int) $id)->unique()->values();
        $baru = collect($siswaIds)->diff($terisi)->values();

        if ($terisi->count() + $baru->count() <= $maks) {
            return null;
        }

        $sisa = max(0, $maks - $terisi->count());
        $label = PenjadwalanSiswa::labelJam($tujuan) . ' (Guru ' . ($tujuan->guru?->nama ?? '-') . ')';

        return 'Kuota jadwal pengganti tidak mencukupi. ' . $label . ' pada ' . Carbon::parse($tanggal)->format('d-m-Y')
            . ' berkapasitas ' . $maks . ' siswa, sudah terisi ' . $terisi->count()
            . ' (peserta tetap dan siswa dari jadwal pengganti lain), sisa ' . $sisa
            . ', sedangkan siswa baru yang akan ikut berjumlah ' . $baru->count() . '.';
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
        int|string|null $jadwalId = null,
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

            // Siswa baru harus peserta jadwal reguler yang digantikan
            if ($jadwalId && $valid) {
                $peserta = DB::table('jadwal_siswa')
                    ->where('jadwal_id', $jadwalId)
                    ->whereIn('siswa_id', $valid)
                    ->pluck('siswa_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $bukanPeserta = array_diff($valid, $peserta);

                if ($bukanPeserta) {
                    $pesan[] = 'Bukan peserta jadwal yang digantikan: '
                        . Siswa::withTrashed()->whereIn('id', $bukanPeserta)->pluck('nama')->implode(', ') . '.';
                }
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

    /** Catatan guru + penanda; jika catatan kosong hanya penanda. */
    public static function gabungKeterangan(?string $catatan): string
    {
        $catatan = trim((string) $catatan);

        return $catatan === '' ? self::TEKS_PENANDA : $catatan . ' — ' . self::TEKS_PENANDA;
    }

    /** Kebalikan gabungKeterangan: hanya catatan guru, untuk ditampilkan lagi di form. */
    public static function ambilCatatan(?string $keterangan): string
    {
        $keterangan = trim((string) $keterangan);
        $akhiran = ' — ' . self::TEKS_PENANDA;

        if ($keterangan === self::TEKS_PENANDA) {
            return '';
        }

        return str_ends_with($keterangan, $akhiran)
            ? substr($keterangan, 0, -strlen($akhiran))
            : $keterangan;
    }

    /**
     * Tanggal pengganti harus di bulan (dan tahun) yang sama dengan tanggal seharusnya.
     * Mengembalikan pesan jika berbeda, atau null jika sudah sama.
     */
    public static function pesanBedaBulan(string $tanggalSeharusnya, string $tanggalPengganti): ?string
    {
        $seharusnya = Carbon::parse($tanggalSeharusnya);
        $pengganti = Carbon::parse($tanggalPengganti);

        if ($seharusnya->format('Y-m') === $pengganti->format('Y-m')) {
            return null;
        }

        return 'Tanggal pengganti (' . $pengganti->locale('id')->translatedFormat('F Y')
            . ') harus berada di bulan yang sama dengan tanggal seharusnya ('
            . $seharusnya->locale('id')->translatedFormat('F Y') . ').';
    }
}