<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PenjadwalanSiswa
{
    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public static function label(Jadwal $jadwal): string
    {
        return $jadwal->hari . ' ' . substr($jadwal->jam_mulai, 0, 5) . '–' . substr($jadwal->jam_selesai, 0, 5)
            . ' · Guru ' . ($jadwal->guru?->nama ?? '-');
    }

    private static function jadwalAktif(array $kelasIds): Collection
    {
        return Jadwal::query()
            ->with(['kelas', 'guru'])
            ->whereIn('kelas_id', $kelasIds)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Jadwal $j) => [
                $j->kelas?->nama_kelas,
                array_search($j->hari, self::HARI),
                $j->jam_mulai,
            ])
            ->values();
    }

    /** Opsi "Pilih jam". $kelompok = true: dikelompokkan per program. */
    public static function opsi(array $kelasIds, bool $kelompok = true): array
    {
        $jadwal = self::jadwalAktif($kelasIds);

        if (! $kelompok) {
            return $jadwal->mapWithKeys(fn (Jadwal $j) => [$j->id => self::label($j)])->all();
        }

        return $jadwal
            ->groupBy(fn (Jadwal $j) => $j->kelas?->nama_kelas ?? '-')
            ->map(fn (Collection $g) => $g->mapWithKeys(fn (Jadwal $j) => [$j->id => self::label($j)])->all())
            ->all();
    }

    /** Id semua jadwal aktif dari program-program ini. */
    public static function idJadwalDi(array $kelasIds): array
    {
        return Jadwal::query()
            ->whereIn('kelas_id', $kelasIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Jam (jadwal aktif) yang tersimpan untuk siswa di program-program ini. */
    public static function idTersimpan(int $siswaId, array $kelasIds): array
    {
        if ($kelasIds === []) {
            return [];
        }

        return DB::table('jadwal_siswa')
            ->join('jadwal', 'jadwal.id', '=', 'jadwal_siswa.jadwal_id')
            ->where('jadwal_siswa.siswa_id', $siswaId)
            ->whereIn('jadwal.kelas_id', $kelasIds)
            ->where('jadwal.is_active', true)
            ->pluck('jadwal.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Label jam yang dipilih siswa di satu program (opsional: hanya sesi satu guru). */
    public static function labelTersimpan(int $siswaId, int $kelasId, ?int $guruId = null): array
    {
        return Jadwal::query()
            ->with('guru')
            ->where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->when($guruId, fn ($q) => $q->where('guru_id', $guruId))
            ->whereIn('id', fn ($q) => $q->select('jadwal_id')->from('jadwal_siswa')->where('siswa_id', $siswaId))
            ->get()
            ->map(fn (Jadwal $j) => self::label($j))
            ->all();
    }

    /**
     * Program yang WAJIB dipilihkan jam: program baru/diaktifkan lagi,
     * dan program yang sebelumnya sudah punya jam (tidak boleh dikosongkan).
     * Program lama tanpa data jam (sebelum fitur ini ada) tidak dipaksa.
     */
    public static function kelasWajibPilih(?int $siswaId, array $kelasDipilih, array $kelasAktif): array
    {
        $baru = array_diff($kelasDipilih, $kelasAktif);

        $sudahPunya = $siswaId
            ? Jadwal::whereIn('id', self::idTersimpan($siswaId, $kelasAktif))
                ->pluck('kelas_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return array_values(array_unique([...$baru, ...array_intersect($kelasDipilih, $sudahPunya)]));
    }

    /** Nama program (yang punya jadwal aktif) tapi belum ada jam yang dipilih. */
    public static function namaKelasTanpaPilihan(array $kelasIds, array $jadwalIds): Collection
    {
        if ($kelasIds === []) {
            return collect();
        }

        $dipilih = Jadwal::whereIn('id', $jadwalIds)->pluck('kelas_id');

        return Kelas::query()
            ->whereIn('id', $kelasIds)
            ->whereHas('jadwal', fn ($q) => $q->where('is_active', true))
            ->whereNotIn('id', $dipilih)
            ->pluck('nama_kelas');
    }

    /**
     * Bentrok jam: sesama jam yang dipilih, dan (jika $siswaId diisi)
     * terhadap jam aktif siswa di program lain. Jam yang hanya bersinggungan
     * (selesai 09:00, mulai 09:00) tidak dianggap bentrok.
     */
    public static function pesanBentrok(array $jadwalIds, ?int $siswaId = null): ?string
    {
        if ($jadwalIds === []) {
            return null;
        }

        $baru = Jadwal::with('kelas')->whereIn('id', $jadwalIds)->get()->values();
        $pesan = [];

        foreach ($baru as $i => $a) {
            foreach ($baru->slice($i + 1) as $b) {
                if (self::beririsan($a, $b)) {
                    $pesan[] = self::teksBentrok($a, $b);
                }
            }
        }

        if ($siswaId) {
            $lama = Jadwal::with('kelas')
                ->where('is_active', true)
                ->whereNotIn('id', $jadwalIds)
                ->whereIn('id', fn ($q) => $q->select('jadwal_siswa.jadwal_id')
                    ->from('jadwal_siswa')
                    ->join('kelas_siswa', 'kelas_siswa.id', '=', 'jadwal_siswa.kelas_siswa_id')
                    ->where('jadwal_siswa.siswa_id', $siswaId)
                    ->where('kelas_siswa.is_active', true))
                ->get();

            foreach ($baru as $a) {
                foreach ($lama as $b) {
                    if (self::beririsan($a, $b)) {
                        $pesan[] = self::teksBentrok($a, $b);
                    }
                }
            }
        }

        return $pesan === [] ? null : implode(' ', array_unique($pesan));
    }

    private static function beririsan(Jadwal $a, Jadwal $b): bool
    {
        return $a->hari === $b->hari
            && $a->jam_mulai < $b->jam_selesai
            && $a->jam_selesai > $b->jam_mulai;
    }

    private static function teksBentrok(Jadwal $a, Jadwal $b): string
    {
        $waktu = fn (Jadwal $j) => $j->hari . ' ' . substr($j->jam_mulai, 0, 5) . '–' . substr($j->jam_selesai, 0, 5);

        return 'Jam bentrok: ' . ($a->kelas?->nama_kelas ?? '-') . ' (' . $waktu($a) . ') dengan '
            . ($b->kelas?->nama_kelas ?? '-') . ' (' . $waktu($b) . ').';
    }

    /** Simpan pilihan jam siswa di satu program (program boleh nonaktif). */
    public static function simpanUntukKelas(int $siswaId, int $kelasId, array $jadwalIds): void
    {
        $pivotId = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('kelas_id', $kelasId)
            ->value('id');

        $aktif = Jadwal::query()
            ->where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Program tanpa jadwal aktif: tidak ada yang bisa dipilih, data lama dibiarkan
        if (! $pivotId || $aktif === []) {
            return;
        }

        $dipilih = array_values(array_intersect(array_map('intval', $jadwalIds), $aktif));

        DB::transaction(function () use ($siswaId, $pivotId, $aktif, $dipilih) {
            DB::table('jadwal_siswa')
                ->where('siswa_id', $siswaId)
                ->whereIn('jadwal_id', $aktif)
                ->whereNotIn('jadwal_id', $dipilih)
                ->delete();

            $sudah = DB::table('jadwal_siswa')
                ->where('siswa_id', $siswaId)
                ->whereIn('jadwal_id', $dipilih)
                ->pluck('jadwal_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $baris = collect(array_diff($dipilih, $sudah))
                ->map(fn ($id) => [
                    'jadwal_id' => $id,
                    'siswa_id' => $siswaId,
                    'kelas_siswa_id' => $pivotId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($baris !== []) {
                DB::table('jadwal_siswa')->insert($baris);
            }
        });
    }

    /** Dipakai form siswa: sinkronkan pilihan jam untuk semua program yang aktif. */
    public static function sinkron(Siswa $siswa, array $jadwalIds): void
    {
        $kelasAktif = DB::table('kelas_siswa')
            ->where('siswa_id', $siswa->getKey())
            ->where('is_active', true)
            ->pluck('kelas_id');

        foreach ($kelasAktif as $kelasId) {
            self::simpanUntukKelas($siswa->getKey(), (int) $kelasId, $jadwalIds);
        }
    }

    /** Opsi "Pilih jam" untuk satu sesi (satu guru di satu program). */
    public static function opsiSesi(int $kelasId, int $guruId): array
    {
        return Jadwal::query()
            ->where('kelas_id', $kelasId)
            ->where('guru_id', $guruId)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Jadwal $j) => [array_search($j->hari, self::HARI), $j->jam_mulai])
            ->mapWithKeys(fn (Jadwal $j) => [
                $j->id => $j->hari . ' ' . substr($j->jam_mulai, 0, 5) . '–' . substr($j->jam_selesai, 0, 5),
            ])
            ->all();
    }

    /**
     * Tambahkan siswa ke jadwal-jadwal ini TANPA menghapus jam di sesi lain.
     * (simpanUntukKelas() menyamakan seluruh jam program, jadi tidak cocok di sini.)
     */
    public static function tambahKeJadwal(int $siswaId, int $kelasId, array $jadwalIds): void
    {
        $pivotId = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('kelas_id', $kelasId)
            ->value('id');

        $valid = Jadwal::query()
            ->where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->whereIn('id', $jadwalIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! $pivotId || $valid === []) {
            return;
        }

        $sudah = DB::table('jadwal_siswa')
            ->where('siswa_id', $siswaId)
            ->whereIn('jadwal_id', $valid)
            ->pluck('jadwal_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $baris = collect(array_diff($valid, $sudah))
            ->map(fn ($id) => [
                'jadwal_id' => $id,
                'siswa_id' => $siswaId,
                'kelas_siswa_id' => $pivotId,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($baris !== []) {
            DB::table('jadwal_siswa')->insert($baris);
        }
    }
}