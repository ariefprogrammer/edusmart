<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LaporanSiswaAktifService
{
    /**
     * @param  string      $bulan      Bulan kalender, format Y-m
     * @param  array|null  $cabangIds  null = semua cabang
     */
    public function hitung(string $bulan, ?array $cabangIds = null, ?int $kelasId = null): array
    {
        $awalBulan = CarbonImmutable::createFromFormat('!Y-m', $bulan);
        $akhirBulan = $awalBulan->endOfMonth();
        $hariIni = CarbonImmutable::today();

        // Bulan berjalan: akhir bulannya belum tiba, jadi posisi diukur per hari ini
        $bulanBerjalan = $hariIni->between($awalBulan, $akhirBulan);
        $akhir = $bulanBerjalan ? $hariIni : $akhirBulan;

        $awalBulanStr = $awalBulan->toDateString();
        $awalStr = $awalBulan->subDay()->toDateString(); // posisi akhir bulan lalu
        $akhirStr = $akhir->toDateString();

        $peristiwa = DB::table('kelas_siswa_detail as d')
            ->join('kelas_siswa as ks', 'ks.id', '=', 'd.kelas_siswa_id')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->join('kelas as k', 'k.id', '=', 'ks.kelas_id')
            ->whereNull('s.deleted_at') // siswa yang di-soft-delete dikecualikan
            ->where('d.tanggal', '<=', $akhirStr)
            ->when($cabangIds !== null, fn ($q) => $q
                ->whereIn('s.cabang_id', $cabangIds)
                ->whereIn('k.cabang_id', $cabangIds))
            ->when($kelasId, fn ($q) => $q->where('ks.kelas_id', $kelasId))
            ->orderBy('d.kelas_siswa_id')
            ->orderBy('d.tanggal')
            ->orderBy('d.id')
            ->get([
                'd.kelas_siswa_id',
                'd.status',
                'd.tanggal',
                'ks.siswa_id',
                'k.nama_kelas',
                's.nama as nama_siswa',
            ]);

        // 1) Bentuk periode aktif per baris kelas_siswa, lalu kumpulkan per siswa
        $siswa = [];

        foreach ($peristiwa->groupBy('kelas_siswa_id') as $rows) {
            $pertama = $rows->first();
            $segmen = [];       // [mulai, selesai] dengan selesai eksklusif, null = masih berjalan
            $terbuka = null;
            $pertamaAktif = null;

            foreach ($rows as $row) {
                $tanggal = substr((string) $row->tanggal, 0, 10);

                if ($row->status === 'aktif') {
                    $pertamaAktif ??= $tanggal;
                    $terbuka ??= $tanggal;          // 'aktif' berulang diabaikan
                } elseif ($terbuka !== null) {      // 'nonaktif' berulang diabaikan
                    if ($terbuka < $tanggal) {
                        $segmen[] = [$terbuka, $tanggal];
                    }
                    $terbuka = null;
                }
            }

            if ($terbuka !== null) {
                $segmen[] = [$terbuka, null];
            }

            $aktifAkhir = self::aktifPada($segmen, $akhirStr);

            if ($aktifAkhir) {
                $sejak = null;
                foreach ($segmen as [$mulai, $selesai]) {
                    if ($mulai <= $akhirStr && ($selesai === null || $akhirStr < $selesai)) {
                        $sejak = $mulai;
                        break;
                    }
                }
            } else {
                $terakhir = end($segmen);
                $sejak = $terakhir ? $terakhir[1] : substr((string) $pertama->tanggal, 0, 10);
            }

            $id = $pertama->siswa_id;

            $siswa[$id] ??= [
                'id' => $id,
                'nama' => $pertama->nama_siswa,
                'program' => [],
                'segmen' => [],
                'pertama_aktif' => null,
            ];

            $siswa[$id]['program'][] = [
                'nama' => $pertama->nama_kelas,
                'aktif' => $aktifAkhir,
                'sejak' => $sejak,
            ];

            array_push($siswa[$id]['segmen'], ...$segmen);

            if ($pertamaAktif !== null
                && ($siswa[$id]['pertama_aktif'] === null || $pertamaAktif < $siswa[$id]['pertama_aktif'])) {
                $siswa[$id]['pertama_aktif'] = $pertamaAktif;
            }
        }

        // 2) Status tingkat siswa: aktif jika minimal satu program aktif
        $ringkasan = [
            'aktif' => 0,
            'tidak_aktif' => 0,
            'total' => 0,
            'aktif_awal' => 0,
            'baru' => 0,
            'kembali' => 0,
            'berhenti' => 0,
            'masuk_keluar' => 0,
        ];

        $daftar = [];

        foreach ($siswa as $s) {
            $aktifAwal = self::aktifPada($s['segmen'], $awalStr);
            $aktifAkhir = self::aktifPada($s['segmen'], $akhirStr);

            $gerak = match (true) {
                $aktifAwal && $aktifAkhir => 'tetap',
                $aktifAwal => 'berhenti',
                $aktifAkhir => $s['pertama_aktif'] > $awalStr ? 'baru' : 'kembali',
                collect($s['segmen'])->contains(fn ($seg) => $seg[0] >= $awalBulanStr) => 'masuk_keluar',
                default => null,
            };

            $ringkasan['total']++;
            $ringkasan[$aktifAkhir ? 'aktif' : 'tidak_aktif']++;

            if ($aktifAwal) {
                $ringkasan['aktif_awal']++;
            }

            if (in_array($gerak, ['baru', 'kembali', 'berhenti', 'masuk_keluar'], true)) {
                $ringkasan[$gerak]++;
            }

            $program = $s['program'];
            usort($program, fn ($a, $b) => [(int) ! $a['aktif'], $a['nama']] <=> [(int) ! $b['aktif'], $b['nama']]);

            $daftar[] = [
                'id' => $s['id'],
                'nama' => $s['nama'],
                'status' => $aktifAkhir ? 'aktif' : 'tidak_aktif',
                'gerak' => $gerak,
                'program' => $program,
            ];
        }

        usort($daftar, fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));

        return [
            'awal' => $awalStr,
            'akhir' => $akhirStr,
            'bulan_berjalan' => $bulanBerjalan,
            'ringkasan' => $ringkasan,
            'siswa' => $daftar,
        ];
    }

    /** Aktif pada tanggal X jika ada periode yang mencakup X (selesai bersifat eksklusif). */
    private static function aktifPada(array $segmen, string $tanggal): bool
    {
        foreach ($segmen as [$mulai, $selesai]) {
            if ($mulai <= $tanggal && ($selesai === null || $tanggal < $selesai)) {
                return true;
            }
        }

        return false;
    }
}