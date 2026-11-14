<?php

namespace App\Filament\Resources\KelasResource\RelationManagers;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KelasSiswa;
use App\Models\KelasSiswaFollowUp;
use App\Models\Siswa;
use App\Services\PenjadwalanSiswa;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SiswaRelationManager extends RelationManager
{
    // Di tab sesi, baris tabel = jam (jadwal). Tab lain memakai baris siswa (lihat getRelationship).
    protected static string $relationship = 'jadwal';
    protected static ?string $title = 'Siswa Terdaftar';

    protected ?array $tabsCache = null;
    protected ?Collection $jumlahJamCache = null;
    protected ?array $programAktifCache = null;

    // =========================================================
    // Tab sesi (satu guru = satu sesi)
    // =========================================================

    public function getRelationship(): Relation | Builder
    {
        $kelas = $this->getOwnerRecord();

        return $this->sesiAktif() !== null ? $kelas->jadwal() : $kelas->siswa();
    }

    public function getTabs(): array
    {
        if ($this->tabsCache !== null) {
            return $this->tabsCache;
        }

        $kelas = $this->getOwnerRecord();
        $sesi = $kelas->sesiGuru();

        // Program tanpa jadwal aktif: satu daftar siswa, tanpa kuota (kuota berlaku per jam)
        if ($sesi->isEmpty()) {
            return $this->tabsCache = ['semua' => Tab::make('Semua Siswa')];
        }

        $perJam = $this->jumlahPerJam();
        $perSesi = $kelas->jumlahSiswaAktifPerSesi();
        $maks = $kelas->kuota_maksimal;
        $min = (int) $kelas->kuota_minimal;
        $jamPerGuru = $kelas->jadwal()->where('is_active', true)->get()->groupBy('guru_id');
        $tabs = [];

        foreach ($sesi as $guru) {
            $jam = $jamPerGuru->get($guru->id, collect());
            $penuh = $maks === null ? 0 : $jam->filter(fn (Jadwal $j) => (int) $perJam->get($j->id, 0) >= $maks)->count();
            $kurang = $min > 0 ? $jam->filter(fn (Jadwal $j) => (int) $perJam->get($j->id, 0) < $min)->count() : 0;

            $tabs['sesi-' . $guru->id] = Tab::make($guru->nama)
                ->badge((int) $perSesi->get($guru->id, 0))
                ->badgeColor(match (true) {
                    $jam->isNotEmpty() && $penuh === $jam->count() => 'danger',
                    $kurang > 0 => 'warning',
                    default => 'success',
                });
        }

        // Siswa program yang belum punya jam di sesi mana pun (mis. siswa lama)
        $tanpaSesi = $kelas->siswa()->whereNotIn('siswa.id', $kelas->subIdSiswaSesi())->count();

        if ($tanpaSesi > 0) {
            $tabs['tanpa-sesi'] = Tab::make('Belum Memilih Sesi')
                ->badge($tanpaSesi)
                ->badgeColor('gray');
        }

        return $this->tabsCache = $tabs;
    }

    protected function tabAktif(): ?string
    {
        $kunci = array_keys($this->getTabs());

        return in_array($this->activeTab, $kunci, true) ? $this->activeTab : ($kunci[0] ?? null);
    }

    /** Id guru dari tab sesi yang sedang dibuka; null di tab lain. */
    protected function sesiAktif(): ?int
    {
        $tab = (string) $this->tabAktif();

        return str_starts_with($tab, 'sesi-') ? (int) substr($tab, 5) : null;
    }

    protected function namaSesiAktif(): string
    {
        $guruId = $this->sesiAktif();

        return $guruId ? (Guru::find($guruId)?->nama ?? '-') : '-';
    }

    protected function segarkanCache(): void
    {
        $this->tabsCache = null;
        $this->jumlahJamCache = null;
        $this->programAktifCache = null;
    }

    // =========================================================
    // Kuota per jam
    // =========================================================

    protected function jumlahPerJam(): Collection
    {
        return $this->jumlahJamCache ??= $this->getOwnerRecord()->jumlahSiswaAktifPerJadwal();
    }

    protected function teksKuota(int $jadwalId): string
    {
        $kelas = $this->getOwnerRecord();
        $terisi = (int) $this->jumlahPerJam()->get($jadwalId, 0);
        $maks = $kelas->kuota_maksimal;

        if ($maks === null) {
            return "{$terisi} siswa";
        }

        if ($terisi >= $maks) {
            return "{$terisi}/{$maks} · Penuh";
        }

        $teks = "{$terisi}/{$maks} · Sisa " . ($maks - $terisi);

        return $kelas->statusKuota($terisi) === 'kurang' ? $teks . ' · Di bawah minimal' : $teks;
    }

    protected function warnaKuota(int $jadwalId): string
    {
        $kelas = $this->getOwnerRecord();
        $terisi = (int) $this->jumlahPerJam()->get($jadwalId, 0);

        return match (true) {
            $kelas->kuota_maksimal !== null && $terisi >= $kelas->kuota_maksimal => 'danger',
            $kelas->statusKuota($terisi) === 'kurang' => 'warning',
            default => 'success',
        };
    }

    protected function idJamAktifSesi(int $guruId): array
    {
        return Jadwal::query()
            ->where('kelas_id', $this->getOwnerRecord()->getKey())
            ->where('guru_id', $guruId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Jam (jadwal) yang diklik di tabel, dikirim lewat argumen aksi. Null di tab tanpa jam. */
    protected function jamAsal(array $arguments): ?Jadwal
    {
        $id = (int) ($arguments['jadwal'] ?? 0);

        return $id
            ? Jadwal::with('guru')
                ->where('kelas_id', $this->getOwnerRecord()->getKey())
                ->whereKey($id)
                ->first()
            : null;
    }

    /** Pastikan siswa memang terdaftar di jam yang dimaksud. */
    protected function pastikanJamSiswa(array $arguments, Siswa $siswa, Tables\Actions\Action $action): Jadwal
    {
        $jam = $this->jamAsal($arguments);

        $terdaftar = $jam && DB::table('jadwal_siswa')
            ->where('siswa_id', $siswa->getKey())
            ->where('jadwal_id', $jam->getKey())
            ->exists();

        if (! $terdaftar) {
            Notification::make()
                ->title('Siswa tidak terdaftar di jam ini')
                ->body('Data mungkin sudah berubah. Tutup modal lalu coba lagi.')
                ->danger()
                ->send();

            $action->halt();
        }

        return $jam;
    }

    /**
     * Jam tujuan pindah, dikelompokkan per guru, berlabel kuota.
     * Tidak termasuk jam asal dan jam yang sudah diikuti siswa.
     */
    protected function opsiJamTujuan(?Siswa $siswa, ?Jadwal $jamAsal): array
    {
        if (! $siswa || ! $jamAsal) {
            return [];
        }

        $kelas = $this->getOwnerRecord();
        $kecuali = array_merge(
            PenjadwalanSiswa::idTersimpan($siswa->getKey(), [$kelas->getKey()]),
            [(int) $jamAsal->getKey()],
        );

        $opsi = [];

        foreach ($kelas->sesiGuru() as $guru) {
            $jam = array_diff_key(
                PenjadwalanSiswa::opsiJamSesiDenganKuota($kelas, $guru->id),
                array_flip($kecuali),
            );

            if ($jam !== []) {
                $opsi['Guru ' . $guru->nama] = $jam;
            }
        }

        return $opsi;
    }

    // =========================================================
    // Helper siswa
    // =========================================================

    protected function siswaDariArgumen(array $arguments): ?Siswa
    {
        return $this->getOwnerRecord()
            ->siswa()
            ->whereKey((int) ($arguments['siswa'] ?? 0))
            ->first();
    }

    protected function pastikanSiswa(array $arguments, Tables\Actions\Action $action): Siswa
    {
        $siswa = $this->siswaDariArgumen($arguments);

        if (! $siswa) {
            Notification::make()->title('Siswa tidak ditemukan di program ini')->danger()->send();

            $action->halt();
        }

        return $siswa;
    }

    protected function programAktifSiswa(): array
    {
        return $this->programAktifCache ??= KelasSiswa::query()
            ->where('kelas_id', $this->getOwnerRecord()->getKey())
            ->pluck('is_active', 'siswa_id')
            ->map(fn ($aktif) => (bool) $aktif)
            ->all();
    }

    protected function sudahDiProgram(mixed $siswaId): bool
    {
        return filled($siswaId) && KelasSiswa::query()
            ->where('kelas_id', $this->getOwnerRecord()->getKey())
            ->where('siswa_id', $siswaId)
            ->exists();
    }

    /** Kuota hanya terhitung bila siswa aktif di program (siswa baru nonaktif tidak dihitung). */
    protected function kuotaBerlaku(mixed $siswaId, mixed $isActive): bool
    {
        return blank($siswaId) || $this->sudahDiProgram($siswaId) || (bool) $isActive;
    }

    protected function bolehTambahSiswa(): bool
    {
        $guruId = $this->sesiAktif();

        if ($guruId) {
            return PenjadwalanSiswa::adaJamTersedia($this->getOwnerRecord(), $guruId);
        }

        return $this->tabAktif() === 'semua';
    }

    /** Siswa yang boleh ditambahkan di tab yang sedang dibuka. */
    protected function kandidatSiswa(): array
    {
        $kelas = $this->getOwnerRecord();
        $guruId = $this->sesiAktif();
        $jamIds = $guruId ? $this->idJamAktifSesi($guruId) : [];

        return Siswa::query()
            ->where('cabang_id', $kelas->cabang_id)
            ->where('is_active', true)
            // Siswa yang nonaktif di program ini diaktifkan lewat Update / CRM
            ->whereNotIn('id', fn ($q) => $q->select('siswa_id')
                ->from('kelas_siswa')
                ->where('kelas_id', $kelas->getKey())
                ->where('is_active', false))
            ->when(
                $guruId,
                // Sesi: dikecualikan hanya siswa yang sudah ada di SEMUA jam sesi ini
                fn ($q) => $q->whereNotIn('id', fn ($sub) => $sub->select('jadwal_siswa.siswa_id')
                    ->from('jadwal_siswa')
                    ->whereIn('jadwal_siswa.jadwal_id', $jamIds ?: [0])
                    ->groupBy('jadwal_siswa.siswa_id')
                    ->havingRaw('COUNT(DISTINCT jadwal_siswa.jadwal_id) >= ?', [max(count($jamIds), 1)])),
                // Tanpa sesi: yang sudah di program tidak ditampilkan
                fn ($q) => $q->whereNotIn('id', fn ($sub) => $sub->select('siswa_id')
                    ->from('kelas_siswa')
                    ->where('kelas_id', $kelas->getKey())),
            )
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /** Jam (jadwal aktif) yang akan diikuti siswa ini bila diaktifkan kembali, tetapi sudah penuh. */
    protected function jamPenuhUntukSiswa(Siswa $siswa): Collection
    {
        $kelas = $this->getOwnerRecord();

        $jamIds = Jadwal::query()
            ->where('kelas_id', $kelas->getKey())
            ->where('is_active', true)
            ->whereIn('id', fn ($q) => $q->select('jadwal_id')
                ->from('jadwal_siswa')
                ->where('siswa_id', $siswa->getKey()))
            ->pluck('id');

        $penuh = PenjadwalanSiswa::idJamPenuh($kelas, $jamIds->all());

        return Jadwal::with('guru')
            ->whereIn('id', $penuh)
            ->get()
            ->map(fn (Jadwal $j) => PenjadwalanSiswa::labelJam($j) . ' (Guru ' . ($j->guru?->nama ?? '-') . ')');
    }

    // =========================================================
    // Tabel
    // =========================================================

    public function table(Table $table): Table
    {
        return $this->sesiAktif() !== null ? $this->tabelJam($table) : $this->tabelSiswa($table);
    }

    /** Tab sesi: satu baris per jam, nama siswa dipisah koma. */
    protected function tabelJam(Table $table): Table
    {
        $kelas = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('jadwal.guru_id', $this->sesiAktif())
                ->where('jadwal.is_active', true)
                ->with(['siswa' => fn ($q) => $q->orderBy('siswa.nama')])
                ->orderByRaw("FIELD(jadwal.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu')")
                ->orderBy('jadwal.jam_mulai'))
            ->description(fn () => 'Sesi ' . $this->namaSesiAktif()
                . ' · Kuota per jam — minimal: ' . $kelas->kuota_minimal
                . ', maksimal: ' . ($kelas->kuota_maksimal ?? 'tanpa batas'))
            ->paginated(false)
            ->emptyStateHeading('Belum ada jam aktif di sesi ini')
            ->columns([
                Tables\Columns\TextColumn::make('jam')
                    ->label('Jam')
                    ->getStateUsing(fn (Jadwal $record) => PenjadwalanSiswa::labelJam($record)),

                Tables\Columns\ViewColumn::make('nama_siswa')
                    ->label('Nama Siswa')
                    ->view('filament.tables.columns.siswa-per-jam')
                    ->viewData(fn () => ['programAktif' => $this->programAktifSiswa()]),

                Tables\Columns\TextColumn::make('kuota')
                    ->label('Kuota')
                    ->badge()
                    ->getStateUsing(fn (Jadwal $record) => $this->teksKuota($record->id))
                    ->color(fn (Jadwal $record) => $this->warnaKuota($record->id)),
            ])
            ->headerActions($this->aksiHeader());
    }

    /** Tab "Belum Memilih Sesi" dan program tanpa jadwal aktif: satu baris per siswa. */
    protected function tabelSiswa(Table $table): Table
    {
        $kelas = $this->getOwnerRecord();
        $tanpaSesi = $this->tabAktif() === 'tanpa-sesi';

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($kelas, $tanpaSesi) {
                if ($tanpaSesi) {
                    $query->whereNotIn('siswa.id', $kelas->subIdSiswaSesi());
                }

                return $query->orderBy('siswa.nama');
            })
            ->description(fn () => $tanpaSesi
                ? 'Siswa program ini yang belum memilih jam di sesi (guru) mana pun. '
                    . 'Tambahkan lewat tab sesi, atau atur jamnya di halaman Siswa.'
                : 'Program ini belum punya jadwal aktif, sehingga belum ada jam dan kuota.')
            ->columns([
                Tables\Columns\TextColumn::make('jam')
                    ->label('Jam')
                    ->getStateUsing(fn () => $tanpaSesi ? 'Belum memilih jam' : 'Belum ada jadwal')
                    ->color('warning'),

                Tables\Columns\ViewColumn::make('nama_siswa')
                    ->label('Nama Siswa')
                    ->view('filament.tables.columns.siswa-per-jam')
                    ->viewData(fn () => ['programAktif' => $this->programAktifSiswa()]),
            ])
            ->headerActions($this->aksiHeader());
    }

    // =========================================================
    // Aksi
    // =========================================================

    /**
     * Satu aksi terlihat (Tambah Siswa) dan aksi siswa yang dibuka lewat klik nama
     * (disembunyikan lewat CSS, dipanggil dengan argumen id siswa).
     */
    protected function aksiHeader(): array
    {
        return [
            $this->aksiTambahSiswa(),
            $this->aksiDetailSiswa(),
            $this->aksiCrmSiswa(),
            $this->aksiRiwayatSiswa(),
            $this->aksiPindahSesi(),
            $this->aksiKeluarkan(),
        ];
    }

    protected function aksiTambahSiswa(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('tambahSiswa')
            ->label('Tambah Siswa')
            ->icon('heroicon-o-plus')
            ->visible(fn () => $this->bolehTambahSiswa())
            ->modalHeading(fn () => $this->sesiAktif()
                ? 'Tambah Siswa — Sesi ' . $this->namaSesiAktif()
                : 'Tambah Siswa')
            ->modalSubmitActionLabel('Tambahkan')
            ->form([
                Forms\Components\Select::make('siswa_id')
                    ->label('Siswa')
                    ->options(fn () => $this->kandidatSiswa())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('jadwal_ids', [])),

                // Hanya untuk siswa yang belum terdaftar di program ini
                Forms\Components\DatePicker::make('tanggal_gabung')
                    ->native(false)
                    ->default(now())
                    ->visible(fn (Get $get) => ! $this->sudahDiProgram($get('siswa_id'))),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif di Program Ini')
                    ->default(true)
                    ->live()
                    ->visible(fn (Get $get) => ! $this->sudahDiProgram($get('siswa_id'))),

                Forms\Components\Select::make('jadwal_ids')
                    ->label('Pilih jam')
                    ->multiple()
                    ->visible(fn () => $this->sesiAktif() !== null)
                    ->required()
                    // Jam yang sudah diikuti siswa tidak ditawarkan lagi
                    ->options(function (Get $get) {
                        $opsi = PenjadwalanSiswa::opsiJamSesiDenganKuota(
                            $this->getOwnerRecord(),
                            (int) $this->sesiAktif(),
                        );

                        $sudah = filled($get('siswa_id'))
                            ? PenjadwalanSiswa::idTersimpan((int) $get('siswa_id'), [$this->getOwnerRecord()->getKey()])
                            : [];

                        return array_diff_key($opsi, array_flip($sudah));
                    })
                    // Jam yang kuotanya penuh tidak bisa dipilih
                    ->disableOptionWhen(fn (string $value, Get $get) => $this->kuotaBerlaku($get('siswa_id'), $get('is_active'))
                        && in_array((int) $value, PenjadwalanSiswa::idJamPenuh($this->getOwnerRecord(), [(int) $value]), true))
                    ->helperText('Pilih satu atau lebih jam. Jam yang penuh tidak dapat dipilih.')
                    ->rule(fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if (blank($get('siswa_id')) || ! $this->kuotaBerlaku($get('siswa_id'), $get('is_active'))) {
                            return;
                        }

                        $pesan = PenjadwalanSiswa::pesanBentrok(
                            array_map('intval', (array) $value),
                            (int) $get('siswa_id'),
                        );

                        if ($pesan) {
                            $fail($pesan);
                        }
                    }),
            ])
            ->action(function (array $data, Tables\Actions\Action $action) {
                $kelas = $this->getOwnerRecord();
                $guruId = $this->sesiAktif();
                $siswaId = (int) ($data['siswa_id'] ?? 0);
                $jadwalIds = array_map('intval', $data['jadwal_ids'] ?? []);

                if (! array_key_exists($siswaId, $this->kandidatSiswa()) || ($guruId && $jadwalIds === [])) {
                    Notification::make()
                        ->title('Siswa tidak dapat ditambahkan')
                        ->body('Siswa tidak aktif, sudah ada di semua jam sesi ini, atau jam belum dipilih.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                // Pengaman di sisi server: kuota per jam
                if ($guruId && $this->kuotaBerlaku($siswaId, $data['is_active'] ?? true)) {
                    $penuh = PenjadwalanSiswa::idJamPenuh($kelas, $jadwalIds);

                    if ($penuh !== []) {
                        Notification::make()
                            ->title('Kuota penuh')
                            ->body(PenjadwalanSiswa::pesanJamPenuh($penuh))
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }

                DB::transaction(function () use ($kelas, $guruId, $siswaId, $jadwalIds, $data) {
                    // Siswa baru di program: buat baris kelas_siswa (riwayat dicatat oleh model pivot)
                    if (! $this->sudahDiProgram($siswaId)) {
                        $kelas->siswa()->attach($siswaId, [
                            'tanggal_gabung' => $data['tanggal_gabung'] ?? now()->toDateString(),
                            'is_active' => (bool) ($data['is_active'] ?? true),
                        ]);
                    }

                    if ($guruId) {
                        PenjadwalanSiswa::tambahKeJadwal($siswaId, $kelas->getKey(), $jadwalIds);
                    }
                });

                $this->segarkanCache();

                Notification::make()->title('Siswa berhasil ditambahkan')->success()->send();
            });
    }

    protected function aksiDetailSiswa(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('detailSiswa')
            ->extraAttributes(['class' => 'hidden'])
            ->modalHeading(fn (array $arguments) => $this->siswaDariArgumen($arguments)?->nama ?? 'Detail Siswa')
            ->modalContent(function (array $arguments) {
                $siswa = $this->siswaDariArgumen($arguments);

                if (! $siswa) {
                    return new HtmlString('<p class="text-sm text-gray-500">Siswa tidak ditemukan di program ini.</p>');
                }

                $jam = $this->jamAsal($arguments);

                return view('filament.partials.detail-siswa-sesi', [
                    'siswa' => $siswa,
                    'jadwalId' => $jam?->getKey() ?? 0,
                    'labelJam' => $jam
                        ? PenjadwalanSiswa::labelJam($jam) . ' · Guru ' . ($jam->guru?->nama ?? '-')
                        : null,
                    // Tombol Pindah hanya muncul bila ada jam tujuan yang bisa dipilih
                    'bisaPindah' => $jam !== null && $this->opsiJamTujuan($siswa, $jam) !== [],
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalWidth('lg');
    }

    protected function aksiRiwayatSiswa(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('riwayatSiswa')
            ->extraAttributes(['class' => 'hidden'])
            ->modalHeading(fn (array $arguments) => 'Riwayat : ' . ($this->siswaDariArgumen($arguments)?->nama ?? ''))
            ->modalContent(function (array $arguments) {
                $siswa = $this->siswaDariArgumen($arguments);

                return view('filament.partials.riwayat-kelas-siswa', [
                    'riwayat' => $siswa ? $siswa->pivot->timeline() : collect(),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }

    protected function aksiCrmSiswa(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('crmSiswa')
            ->extraAttributes(['class' => 'hidden'])
            ->modalHeading(fn (array $arguments) => 'Update / CRM — ' . ($this->siswaDariArgumen($arguments)?->nama ?? ''))
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Simpan')
            // Nilai awal diambil dari pivot (status di program ini), bukan dari data siswa
            ->fillForm(fn (array $arguments): array => [
                'aksi' => 'ubah_status',
                'is_active' => (bool) $this->siswaDariArgumen($arguments)?->pivot->is_active,
                'tanggal_perubahan' => now()->toDateString(),
            ])
            ->form([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Radio::make('aksi')
                            ->label('Aksi')
                            ->options([
                                'ubah_status' => 'Ubah status di program ini',
                                'follow_up' => 'Catat follow-up (ajakan join kembali, dll.)',
                            ])
                            ->default('ubah_status')
                            ->live()
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif di Program Ini')
                            ->helperText('Nonaktifkan jika siswa berhenti atau keluar dari program ini.')
                            ->inline(false)
                            ->visible(fn (Get $get) => $get('aksi') === 'ubah_status'),

                        Forms\Components\Select::make('hasil')
                            ->label('Hasil Follow-up')
                            ->options(KelasSiswaFollowUp::HASIL)
                            ->required()
                            ->visible(fn (Get $get) => $get('aksi') === 'follow_up'),

                        Forms\Components\DatePicker::make('tanggal_perubahan')
                            ->label('Tanggal')
                            ->native(false)
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan / Alasan')
                            ->placeholder('Contoh: pindah ke kelas lain, mengundurkan diri, siswa menolak karena jadwal, dll.')
                            ->rows(3),
                    ])
                    ->columns(1),
            ])
            ->successNotificationTitle('Tersimpan')
            ->action(function (array $data, array $arguments, Tables\Actions\Action $action) {
                $siswa = $this->pastikanSiswa($arguments, $action);

                // Follow-up tidak mengubah status, hanya menulis ke tabel follow-up
                if (($data['aksi'] ?? 'ubah_status') === 'follow_up') {
                    $siswa->pivot->followUp()->create([
                        'tanggal' => $data['tanggal_perubahan'],
                        'hasil' => $data['hasil'],
                        'keterangan' => $data['keterangan'] ?? null,
                        'diubah_oleh' => auth()->id(),
                    ]);

                    $action->success();

                    return;
                }

                $statusBaru = (bool) ($data['is_active'] ?? false);
                $statusLama = (bool) $siswa->pivot->is_active;

                // Status tidak berubah -> jangan dicatat sebagai riwayat status
                if ($statusBaru === $statusLama) {
                    Notification::make()
                        ->title('Status tidak berubah')
                        ->body('Status siswa di program ini sama dengan sebelumnya. Untuk mencatat ajakan atau penolakan, pilih aksi "Catat follow-up".')
                        ->warning()
                        ->send();

                    $action->halt();
                }

                // Diaktifkan kembali: setiap jam yang akan diikuti harus masih punya kuota
                if ($statusBaru && ! $statusLama) {
                    $penuh = $this->jamPenuhUntukSiswa($siswa);

                    if ($penuh->isNotEmpty()) {
                        Notification::make()
                            ->title('Kuota penuh')
                            ->body('Siswa tidak dapat diaktifkan kembali karena kuota jam berikut sudah penuh: ' . $penuh->join('; ') . '.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }

                $statusDikembalikan = false;

                DB::transaction(function () use ($siswa, $data, &$statusDikembalikan) {
                    $siswa->pivot->tanggalPerubahan = $data['tanggal_perubahan'] ?? now()->toDateString();
                    $siswa->pivot->keteranganPerubahan = $data['keterangan'] ?? null;
                    $siswa->pivot->is_active = $data['is_active'];
                    $siswa->pivot->save();

                    if ($data['is_active']) {
                        // Program diaktifkan: siswa yang masih nonaktif atau berstatus cuti/keluar ikut diaktifkan
                        $statusNonaktif = in_array($siswa->status, Siswa::STATUS_NONAKTIF, true);

                        if (! $siswa->is_active || $statusNonaktif) {
                            $siswa->is_active = true;

                            if ($statusNonaktif) {
                                $siswa->status = 'aktif';
                                $statusDikembalikan = true;
                            }

                            $siswa->save();
                        }
                    } else {
                        // Program dinonaktifkan: jika tidak ada program aktif lain, nonaktifkan siswanya
                        $masihPunyaProgramAktif = $siswa->kelasList()
                            ->wherePivot('is_active', true)
                            ->where('kelas.id', '!=', $this->getOwnerRecord()->getKey())
                            ->exists();

                        if (! $masihPunyaProgramAktif) {
                            $siswa->is_active = false;
                            $siswa->save();
                        }
                    }
                });

                $this->segarkanCache();

                if ($statusDikembalikan) {
                    Notification::make()
                        ->title('Status siswa dikembalikan ke aktif')
                        ->body($siswa->nama . ' sebelumnya berstatus cuti/keluar, sehingga status siswa ikut diubah menjadi aktif.')
                        ->info()
                        ->send();
                }

                $action->success();
            });
    }

    protected function aksiPindahSesi(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('pindahSesiSiswa')
            ->extraAttributes(['class' => 'hidden'])
            ->modalHeading(fn (array $arguments) => 'Pindah Sesi — ' . ($this->siswaDariArgumen($arguments)?->nama ?? ''))
            ->modalDescription(function (array $arguments) {
                $jam = $this->jamAsal($arguments);

                return $jam
                    ? 'Dari jam ' . PenjadwalanSiswa::labelJam($jam) . ' (Guru ' . ($jam->guru?->nama ?? '-')
                        . '). Jam ini akan dilepas dan diganti dengan jam tujuan.'
                    : '';
            })
            ->modalSubmitActionLabel('Pindahkan')
            ->form(function (array $arguments) {
                $siswa = $this->siswaDariArgumen($arguments);
                $jamAsal = $this->jamAsal($arguments);
                $aktif = (bool) $siswa?->pivot->is_active;
                $kelas = $this->getOwnerRecord();

                return [
                    Forms\Components\Select::make('jadwal_ids')
                        ->label('Pindah ke jam')
                        ->multiple()
                        ->options(fn () => $this->opsiJamTujuan($siswa, $jamAsal))
                        // Siswa nonaktif tidak terhitung kuota, jadi jam penuh pun boleh dipilih
                        ->disableOptionWhen(fn (string $value) => $aktif
                            && in_array((int) $value, PenjadwalanSiswa::idJamPenuh($kelas, [(int) $value]), true))
                        ->required()
                        ->helperText('Boleh ke guru yang sama atau berbeda, selama jamnya berbeda. Jam yang penuh tidak dapat dipilih.')
                        ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail) use ($siswa, $jamAsal, $aktif) {
                            // Siswa nonaktif di program ini tidak terhitung bentrok
                            if (! $siswa || ! $jamAsal || ! $aktif) {
                                return;
                            }

                            // Jam asal dilepas, jadi tidak dihitung bentrok dengan jam tujuan
                            $pesan = PenjadwalanSiswa::pesanBentrok(
                                array_map('intval', (array) $value),
                                (int) $siswa->getKey(),
                                [(int) $jamAsal->getKey()],
                            );

                            if ($pesan) {
                                $fail($pesan);
                            }
                        }),
                ];
            })
            ->action(function (array $data, array $arguments, Tables\Actions\Action $action) {
                $siswa = $this->pastikanSiswa($arguments, $action);
                $jamAsal = $this->pastikanJamSiswa($arguments, $siswa, $action);
                $kelas = $this->getOwnerRecord();
                $aktif = (bool) $siswa->pivot->is_active;

                // Pengaman di sisi server: jam tujuan harus jam aktif lain yang belum diikuti siswa
                $kecuali = array_merge(
                    PenjadwalanSiswa::idTersimpan($siswa->getKey(), [$kelas->getKey()]),
                    [(int) $jamAsal->getKey()],
                );

                $jadwalTujuan = array_values(array_intersect(
                    array_map('intval', $data['jadwal_ids'] ?? []),
                    Jadwal::query()
                        ->where('kelas_id', $kelas->getKey())
                        ->where('is_active', true)
                        ->whereNotIn('id', $kecuali)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all(),
                ));

                if ($jadwalTujuan === []) {
                    Notification::make()
                        ->title('Jam tujuan tidak valid')
                        ->body('Pilih jam aktif lain yang belum diikuti siswa ini.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                // Kuota per jam tujuan (hanya untuk siswa yang aktif di program)
                if ($aktif) {
                    $penuh = PenjadwalanSiswa::idJamPenuh($kelas, $jadwalTujuan);

                    if ($penuh !== []) {
                        Notification::make()
                            ->title('Kuota penuh')
                            ->body(PenjadwalanSiswa::pesanJamPenuh($penuh))
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }

                PenjadwalanSiswa::pindahJam($siswa->getKey(), $kelas->getKey(), $jamAsal->getKey(), $jadwalTujuan);

                $this->segarkanCache();

                $tujuan = Jadwal::with('guru')
                    ->whereIn('id', $jadwalTujuan)
                    ->get()
                    ->map(fn (Jadwal $j) => PenjadwalanSiswa::labelJam($j) . ' (Guru ' . ($j->guru?->nama ?? '-') . ')');

                Notification::make()
                    ->title($siswa->nama . ' dipindahkan')
                    ->body('Dari ' . PenjadwalanSiswa::labelJam($jamAsal) . ' ke ' . $tujuan->join(', ') . '.')
                    ->success()
                    ->send();

                // Jam asal bisa jatuh di bawah kuota minimal setelah ditinggalkan
                if ($aktif && $kelas->kuota_minimal > 0
                    && (int) $this->jumlahPerJam()->get($jamAsal->getKey(), 0) < $kelas->kuota_minimal) {
                    Notification::make()
                        ->title('Jam asal di bawah kuota minimal')
                        ->body(PenjadwalanSiswa::labelJam($jamAsal) . ' kini kurang dari kuota minimal (' . $kelas->kuota_minimal . ').')
                        ->warning()
                        ->send();
                }
            });
    }

    protected function aksiKeluarkan(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('keluarkanSiswa')
            ->extraAttributes(['class' => 'hidden'])
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading(function (array $arguments) {
                $nama = $this->siswaDariArgumen($arguments)?->nama ?? 'siswa';

                return $this->jamAsal($arguments)
                    ? "Keluarkan {$nama} dari jam ini?"
                    : "Keluarkan {$nama} dari program ini?";
            })
            ->modalDescription(function (array $arguments) {
                $siswa = $this->siswaDariArgumen($arguments);
                $jam = $this->jamAsal($arguments);

                // Tab tanpa jam: tetap mengeluarkan dari program
                if (! $jam) {
                    return $this->peringatanKeluarkan(
                        $siswa ? $siswa->pivot->riwayat->count() : 0,
                        $siswa ? $siswa->pivot->followUp->count() : 0,
                    );
                }

                $sisa = $siswa
                    ? count(PenjadwalanSiswa::idTersimpan($siswa->getKey(), [$this->getOwnerRecord()->getKey()])) - 1
                    : 0;

                return new HtmlString(
                    '<p><strong>' . e($siswa?->nama) . '</strong> dikeluarkan dari jam <strong>'
                    . e(PenjadwalanSiswa::labelJam($jam) . ' (Guru ' . ($jam->guru?->nama ?? '-') . ')') . '</strong>.</p>'
                    . '<ul style="list-style: disc; margin: 0.5rem 0 0 1.25rem;">'
                    . '<li>Siswa tetap terdaftar di program ini dan di jam-jam lainnya.</li>'
                    . '<li>Kuota jam ini bertambah satu.</li>'
                    . ($sisa <= 0
                        ? '<li><strong>Ini jam terakhir siswa.</strong> Siswa tetap terdaftar di program, tetapi pindah ke tab "Belum Memilih Sesi".</li>'
                        : '')
                    . '</ul>'
                );
            })
            ->modalSubmitActionLabel(fn (array $arguments) => $this->jamAsal($arguments)
                ? 'Ya, keluarkan dari jam'
                : 'Ya, keluarkan permanen')
            ->action(function (array $arguments, Tables\Actions\Action $action) {
                $siswa = $this->pastikanSiswa($arguments, $action);
                $kelas = $this->getOwnerRecord();

                // Dikeluarkan dari satu jam saja
                if ($this->jamAsal($arguments)) {
                    $jam = $this->pastikanJamSiswa($arguments, $siswa, $action);

                    DB::table('jadwal_siswa')
                        ->where('siswa_id', $siswa->getKey())
                        ->where('jadwal_id', $jam->getKey())
                        ->delete();

                    $this->segarkanCache();

                    Notification::make()
                        ->title($siswa->nama . ' dikeluarkan dari jam ' . PenjadwalanSiswa::labelJam($jam))
                        ->success()
                        ->send();

                    if (PenjadwalanSiswa::idTersimpan($siswa->getKey(), [$kelas->getKey()]) === []) {
                        Notification::make()
                            ->title('Siswa tidak punya jam lagi')
                            ->body($siswa->nama . ' masih terdaftar di program ini dan kini ada di tab "Belum Memilih Sesi".')
                            ->info()
                            ->send();
                    }

                    return;
                }

                // Tab tanpa jam: keluarkan dari program
                $punyaProgramLain = $siswa->kelasList()
                    ->where('kelas.id', '!=', $kelas->getKey())
                    ->exists();

                if (! $punyaProgramLain) {
                    Notification::make()
                        ->title('Siswa tidak dapat dikeluarkan')
                        ->body('Setiap siswa harus terdaftar minimal di satu program (aktif maupun nonaktif).')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $kelas->siswa()->detach($siswa->getKey());

                $this->segarkanCache();

                Notification::make()->title($siswa->nama . ' dikeluarkan dari program')->success()->send();
            });
    }

    protected function peringatanKeluarkan(int $jumlahRiwayat, int $jumlahFollowUp): HtmlString
    {
        return new HtmlString(
            '<p><strong>Fitur ini hanya untuk koreksi salah input.</strong> '
            . 'Jika siswa berhenti atau keluar, gunakan tombol <em>Update / CRM</em> agar riwayatnya tetap tersimpan.</p>'
            . '<p style="margin-top: 0.5rem;">Jika dilanjutkan:</p>'
            . '<ul style="list-style: disc; margin: 0.25rem 0 0 1.25rem;">'
            . '<li>Siswa dikeluarkan dari <strong>program beserta semua sesi (guru)</strong> yang diikutinya.</li>'
            . "<li>{$jumlahRiwayat} catatan riwayat status dan {$jumlahFollowUp} catatan follow-up di program ini akan <strong>dihapus permanen</strong>.</li>"
            . '<li>Tanggal gabung hilang dan tindakan ini <strong>tidak bisa dibatalkan</strong>.</li>'
            . '<li>Siswa yang dikeluarkan tidak akan lagi terhitung di laporan periode lalu untuk program ini.</li>'
            . '</ul>'
        );
    }
}