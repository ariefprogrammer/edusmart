<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\NilaiSiswa;
use App\Models\Periode;
use App\Models\PresensiSiswa;
use App\Models\ProgressSiswa;
use App\Models\ReportSiswa;
use App\Models\Siswa;
use App\Models\User;
use App\Support\ReportKosongException;
use App\Support\ReportSiswaScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membangun snapshot Report Siswa. Service ini SATU-SATUNYA tempat yang melakukan
 * query ke tabel sumber (presensi/progress/nilai). Tampilan dan PDF membaca hasil
 * snapshot saja (ReportSiswa::$data).
 *
 * parameter:
 *   bagian               array  subset dari presensi|progress|nilai
 *   periode_bulan_ids    array  id periode tipe "bulan"    (untuk presensi & progress)
 *   periode_semester_ids array  id periode tipe "semester" (untuk nilai)
 *   kelas_id             ?int   batasi ke satu program (opsional)
 *   catatan              ?string catatan bebas untuk report (opsional, tidak masuk `parameter`)
 *   lewati_kosong        ?bool  lempar ReportKosongException bila siswa tidak punya data sama sekali
 *   batch_uuid           ?string penanda batch (diisi generateBatch, tidak masuk `parameter`)
 */
class ReportSiswaService
{
    public const SCHEMA_VERSION = 1;

    public function generate(Siswa $siswa, array $parameter, ?User $user = null): ReportSiswa
    {
        $user ??= auth()->user();

        $this->pastikanBolehMengakses($siswa, $user);

        $catatan = filled($parameter['catatan'] ?? null) ? trim((string) $parameter['catatan']) : null;
        $batchUuid = filled($parameter['batch_uuid'] ?? null) ? (string) $parameter['batch_uuid'] : null;
        $lewatiKosong = (bool) ($parameter['lewati_kosong'] ?? false);
        $parameter = $this->normalisasiParameter($parameter);
        $bagian = $parameter['bagian'];

        $periodeBulan = $this->ambilPeriode($siswa, $parameter['periode_bulan_ids'], 'bulan');
        $periodeSemester = $this->ambilPeriode($siswa, $parameter['periode_semester_ids'], 'semester');
        $kelasIds = $this->tentukanKelasIds($parameter['kelas_id'], $user);

        $siswa->loadMissing(['cabang', 'waliMurid']);

        $data = [
            'schema_version' => self::SCHEMA_VERSION,
            'dibuat' => [
                'pada' => now()->toIso8601String(),
                'oleh' => ['id' => $user->id, 'nama' => $user->name],
            ],
            'cabang' => [
                'id' => $siswa->cabang?->id,
                'nama' => $siswa->cabang?->nama_cabang,
                'alamat' => $siswa->cabang?->alamat,
            ],
            'siswa' => [
                'id' => $siswa->id,
                'nama' => $siswa->nama,
                'tanggal_lahir' => $siswa->tanggal_lahir?->toDateString(),
                'wali' => $siswa->waliMurid?->nama,
            ],
            'bagian' => $bagian,
            'periode' => [
                'bulan' => $periodeBulan->map(fn (Periode $p) => $this->ringkasPeriode($p))->values()->all(),
                'semester' => $periodeSemester->map(fn (Periode $p) => $this->ringkasPeriode($p))->values()->all(),
            ],
        ];

        if (in_array('presensi', $bagian, true)) {
            $data['presensi'] = $this->presensi($siswa, $periodeBulan, $kelasIds);
        }

        if (in_array('progress', $bagian, true)) {
            $data['progress'] = $this->progress($siswa, $periodeBulan, $kelasIds);
        }

        if (in_array('nilai', $bagian, true)) {
            $data['nilai'] = $this->nilai($siswa, $periodeSemester, $kelasIds);
        }

        if ($lewatiKosong && ! $this->adaData($data)) {
            throw new ReportKosongException($siswa->nama.' tidak punya data pada periode yang dipilih.');
        }

        return DB::transaction(fn () => ReportSiswa::create([
            'batch_uuid' => $batchUuid,
            'cabang_id' => $siswa->cabang_id,
            'siswa_id' => $siswa->id,
            'siswa_nama' => $siswa->nama,
            'cabang_nama' => $siswa->cabang?->nama_cabang,
            'periode_ringkasan' => $this->ringkasanPeriode($periodeBulan, $periodeSemester),
            'parameter' => $parameter,
            'data' => $data,
            'schema_version' => self::SCHEMA_VERSION,
            'generated_by' => $user->id,
            'generated_by_nama' => $user->name,
            'generated_at' => now(),
            'catatan' => $catatan,
        ]));
    }

    /**
     * Buat satu snapshot per siswa. Tiap siswa independen: yang gagal atau dilewati tidak
     * membatalkan yang lain. Filter yang tidak valid gagal cepat (ValidationException).
     *
     * @param  iterable<Siswa>  $siswaList
     * @return array{batch_uuid: string, berhasil: Collection, dilewati: array, gagal: array}
     */
    public function generateBatch(iterable $siswaList, array $parameter, ?User $user = null): array
    {
        $user ??= auth()->user();

        $this->normalisasiParameter($parameter);

        @set_time_limit(300);

        $batchUuid = (string) Str::uuid();
        $berhasil = collect();
        $dilewati = [];
        $gagal = [];

        foreach ($siswaList as $siswa) {
            try {
                $berhasil->push($this->generate($siswa, $parameter + ['batch_uuid' => $batchUuid], $user));
            } catch (ReportKosongException) {
                $dilewati[] = $siswa->nama;
            } catch (ValidationException $e) {
                $gagal[] = ['nama' => $siswa->nama, 'pesan' => collect($e->errors())->flatten()->first() ?? 'Data tidak valid.'];
            } catch (AuthorizationException $e) {
                $gagal[] = ['nama' => $siswa->nama, 'pesan' => $e->getMessage() ?: 'Tidak berhak.'];
            }
        }

        return [
            'batch_uuid' => $batchUuid,
            'berhasil' => $berhasil,
            'dilewati' => $dilewati,
            'gagal' => $gagal,
        ];
    }

    private function adaData(array $data): bool
    {
        if (! empty($data['presensi']['per_kelas']) || ! empty($data['nilai']['per_kelas'])) {
            return true;
        }

        foreach ($data['progress'] ?? [] as $blok) {
            if (! empty($blok['items'])) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- validasi

    private function pastikanBolehMengakses(Siswa $siswa, ?User $user): void
    {
        if (! $user) {
            throw new AuthorizationException('Anda harus login untuk membuat report.');
        }

        $boleh = ReportSiswaScope::siswaQuery($user, null, true)
            ->whereKey($siswa->getKey())
            ->exists();

        if (! $boleh) {
            throw new AuthorizationException('Anda tidak berhak membuat report untuk siswa ini.');
        }
    }

    private function normalisasiParameter(array $p): array
    {
        $bagian = array_values(array_intersect(
            array_keys(ReportSiswa::BAGIAN),
            (array) ($p['bagian'] ?? [])
        ));

        if ($bagian === []) {
            throw ValidationException::withMessages(['bagian' => 'Pilih minimal satu bagian report.']);
        }

        $ids = fn (string $key) => collect($p[$key] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $butuhBulan = (bool) array_intersect($bagian, ['presensi', 'progress']);
        $butuhSemester = in_array('nilai', $bagian, true);

        $bulanIds = $butuhBulan ? $ids('periode_bulan_ids') : [];
        $semesterIds = $butuhSemester ? $ids('periode_semester_ids') : [];

        if ($butuhBulan && $bulanIds === []) {
            throw ValidationException::withMessages(['periode_bulan_ids' => 'Pilih minimal satu periode bulan.']);
        }

        if ($butuhSemester && $semesterIds === []) {
            throw ValidationException::withMessages(['periode_semester_ids' => 'Pilih minimal satu periode semester.']);
        }

        return [
            'bagian' => $bagian,
            'periode_bulan_ids' => $bulanIds,
            'periode_semester_ids' => $semesterIds,
            'kelas_id' => filled($p['kelas_id'] ?? null) ? (int) $p['kelas_id'] : null,
        ];
    }

    /** Periode harus milik cabang siswa dan bertipe sesuai; hasil diurutkan kronologis. */
    private function ambilPeriode(Siswa $siswa, array $ids, string $tipe): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $periodes = Periode::query()
            ->where('cabang_id', $siswa->cabang_id)
            ->where('tipe', $tipe)
            ->whereIn('id', $ids)
            ->orderBy('tanggal_mulai')
            ->get();

        if ($periodes->count() !== count($ids)) {
            throw ValidationException::withMessages([
                $tipe === 'bulan' ? 'periode_bulan_ids' : 'periode_semester_ids' => 'Ada periode yang tidak valid untuk cabang siswa ini.',
            ]);
        }

        return $periodes;
    }

    /** null = tidak dibatasi. Guru selalu dibatasi ke program yang dia ajar. */
    private function tentukanKelasIds(?int $kelasId, User $user): ?array
    {
        $kelasGuru = ReportSiswaScope::guruKelasIds($user);

        if ($kelasId !== null) {
            if ($kelasGuru !== null && ! in_array($kelasId, $kelasGuru, true)) {
                throw new AuthorizationException('Anda tidak mengajar program tersebut.');
            }

            return [$kelasId];
        }

        return $kelasGuru;
    }

    // ------------------------------------------------------------------ presensi

    private function presensi(Siswa $siswa, Collection $periodes, ?array $kelasIds): array
    {
        $query = PresensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereNotNull('kelas_id');

        if ($kelasIds !== null) {
            $query->whereIn('kelas_id', $kelasIds);
        }

        $this->batasiKePeriode($query, $periodes);

        $rows = $this->petakanKePeriode($query->get(['kelas_id', 'periode_id', 'tanggal', 'status']), $periodes);
        $namaKelas = $this->namaKelas($rows->pluck('kelas_id'));

        $perKelas = $rows->groupBy('kelas_id')->map(function (Collection $items, $kelasId) use ($periodes, $namaKelas) {
            $perPeriode = $items->groupBy('periode_id');

            return [
                'kelas' => ['id' => (int) $kelasId, 'nama' => $namaKelas[$kelasId] ?? '-'],
                'rows' => $periodes->map(fn (Periode $p) => [
                    'periode' => $this->ringkasPeriode($p),
                ] + $this->hitungPresensi($perPeriode->get($p->id, collect())))->values()->all(),
                'total' => $this->hitungPresensi($items),
            ];
        })->sortBy(fn (array $k) => $k['kelas']['nama'])->values()->all();

        return ['per_kelas' => $perKelas];
    }

    private function hitungPresensi(Collection $items): array
    {
        $hadir = $items->where('status', 'hadir')->count();
        $izin = $items->where('status', 'izin')->count();
        $sakit = $items->where('status', 'sakit')->count();
        $alpa = $items->where('status', 'alpa')->count();
        $total = $hadir + $izin + $sakit + $alpa;

        return [
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpa' => $alpa,
            'total' => $total,
            // Penyebut = pertemuan yang tercatat (bukan jadwal yang seharusnya).
            'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,
        ];
    }

    // ------------------------------------------------------------------ progress

    private function progress(Siswa $siswa, Collection $periodes, ?array $kelasIds): array
    {
        $query = ProgressSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereNotNull('kelas_id')
            ->with(['kelas', 'guru' => fn ($q) => $q->withTrashed()]);

        if ($kelasIds !== null) {
            $query->whereIn('kelas_id', $kelasIds);
        }

        $this->batasiKePeriode($query, $periodes);

        $records = $query->get();

        $rows = $this->petakanKePeriode($records->map(fn (ProgressSiswa $r) => [
            'kelas_id' => $r->kelas_id,
            'periode_id' => $r->periode_id,
            'tanggal' => $r->tanggal,
            'kelas' => $r->kelas?->nama_kelas ?? '-',
            'guru' => $r->guru?->nama,
            'catatan' => trim((string) $r->catatan),
        ]), $periodes)->filter(fn (array $r) => $r['catatan'] !== '');

        return $periodes->map(fn (Periode $p) => [
            'periode' => $this->ringkasPeriode($p),
            'items' => $rows->where('periode_id', $p->id)
                ->sortBy('tanggal')
                ->map(fn (array $r) => [
                    'tanggal' => $r['tanggal'],
                    'kelas' => $r['kelas'],
                    'guru' => $r['guru'],
                    'catatan' => $r['catatan'],
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }

    // --------------------------------------------------------------------- nilai

    private function nilai(Siswa $siswa, Collection $periodes, ?array $kelasIds): array
    {
        $query = NilaiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereNotNull('kelas_id')
            ->whereIn('periode_id', $periodes->pluck('id'))
            ->with('kategoriNilai');

        if ($kelasIds !== null) {
            $query->whereIn('kelas_id', $kelasIds);
        }

        $rows = $query->get();
        $namaKelas = $this->namaKelas($rows->pluck('kelas_id'));
        $daftarPeriode = $periodes->map(fn (Periode $p) => $this->ringkasPeriode($p))->values()->all();

        $perKelas = $rows->groupBy('kelas_id')->map(function (Collection $items, $kelasId) use ($periodes, $namaKelas, $daftarPeriode) {
            $kategori = $items->groupBy('kategori_nilai_id')->map(function (Collection $k) {
                $kat = $k->first()->kategoriNilai;

                return [
                    'nama' => $kat?->nama_kategori ?? '-',
                    'urutan' => (int) ($kat?->urutan ?? 0),
                    'nilai' => $k->mapWithKeys(fn (NilaiSiswa $n) => [
                        $n->periode_id => $n->nilai !== null ? (float) $n->nilai : null,
                    ])->all(),
                ];
            })->sortBy([['urutan', 'asc'], ['nama', 'asc']])->values()->all();

            $rataRata = $periodes->mapWithKeys(function (Periode $p) use ($items) {
                $nilai = $items->where('periode_id', $p->id)
                    ->pluck('nilai')
                    ->filter(fn ($v) => $v !== null)
                    ->map(fn ($v) => (float) $v);

                return [$p->id => $nilai->isEmpty() ? null : round($nilai->avg(), 2)];
            })->all();

            return [
                'kelas' => ['id' => (int) $kelasId, 'nama' => $namaKelas[$kelasId] ?? '-'],
                'periode' => $daftarPeriode,
                'kategori' => $kategori,
                'rata_rata' => $rataRata,
            ];
        })->sortBy(fn (array $k) => $k['kelas']['nama'])->values()->all();

        return ['per_kelas' => $perKelas];
    }

    // ------------------------------------------------------------------- helper

    /**
     * Ambil data yang periode_id-nya cocok, ATAU (data lama tanpa periode_id) yang
     * tanggalnya jatuh di rentang salah satu periode terpilih.
     */
    private function batasiKePeriode($query, Collection $periodes): void
    {
        $query->where(function ($q) use ($periodes) {
            $q->whereIn('periode_id', $periodes->pluck('id'))
                ->orWhere(function ($q2) use ($periodes) {
                    $q2->whereNull('periode_id')->where(function ($q3) use ($periodes) {
                        foreach ($periodes as $p) {
                            $q3->orWhereBetween('tanggal', [
                                $p->tanggal_mulai->toDateString(),
                                $p->tanggal_selesai->toDateString(),
                            ]);
                        }
                    });
                });
        });
    }

    /**
     * Ubah baris (model atau array) menjadi array datar dengan periode_id yang sudah
     * ditentukan (dari periode_id, atau dari tanggal bila NULL). Baris yang tidak
     * masuk periode mana pun dibuang.
     */
    private function petakanKePeriode(Collection $records, Collection $periodes): Collection
    {
        return $records->map(function ($r) use ($periodes) {
            $row = is_array($r) ? $r : [
                'kelas_id' => $r->kelas_id,
                'periode_id' => $r->periode_id,
                'tanggal' => $r->tanggal,
                'status' => $r->status,
            ];

            $tanggal = $row['tanggal']->toDateString();

            $periodeId = $row['periode_id']
                ?: $periodes->first(fn (Periode $p) => $tanggal >= $p->tanggal_mulai->toDateString()
                    && $tanggal <= $p->tanggal_selesai->toDateString())?->id;

            $row['periode_id'] = $periodeId;
            $row['tanggal'] = $tanggal;

            return $row;
        })->filter(fn (array $row) => $row['periode_id'] !== null)->values();
    }

    private function namaKelas(Collection $kelasIds): Collection
    {
        return Kelas::whereIn('id', $kelasIds->unique())->pluck('nama_kelas', 'id');
    }

    private function ringkasPeriode(Periode $p): array
    {
        return [
            'id' => $p->id,
            'nama' => $p->nama_periode,
            'mulai' => $p->tanggal_mulai->toDateString(),
            'selesai' => $p->tanggal_selesai->toDateString(),
        ];
    }

    private function ringkasanPeriode(Collection $bulan, Collection $semester): ?string
    {
        $bagian = [];

        if ($bulan->isNotEmpty()) {
            $bagian[] = 'Bulan: '.$bulan->pluck('nama_periode')->implode(', ');
        }

        if ($semester->isNotEmpty()) {
            $bagian[] = 'Semester: '.$semester->pluck('nama_periode')->implode(', ');
        }

        return $bagian === [] ? null : Str::limit(implode(' · ', $bagian), 500, '…');
    }
}
