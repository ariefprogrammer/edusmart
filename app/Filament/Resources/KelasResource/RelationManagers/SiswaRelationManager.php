<?php

namespace App\Filament\Resources\KelasResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use App\Models\KelasSiswaFollowUp;
use Filament\Forms\Get;
use App\Models\Siswa;
use App\Services\PenjadwalanSiswa;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KelasSiswa;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Set;

class SiswaRelationManager extends RelationManager
{
    protected static string $relationship = 'siswa';
    protected static ?string $inverseRelationship = 'kelasList';
    protected static ?string $recordTitleAttribute = 'nama';
    protected static ?string $title = 'Siswa Terdaftar';

    protected ?array $tabsCache = null;

    public function getTabs(): array
    {
        if ($this->tabsCache !== null) {
            return $this->tabsCache;
        }

        $kelas = $this->getOwnerRecord();
        $sesi = $kelas->sesiGuru();

        // Program tanpa jadwal aktif: perilaku lama (satu daftar, kuota per program)
        if ($sesi->isEmpty()) {
            return $this->tabsCache = ['semua' => Tab::make('Semua Siswa')];
        }

        $jumlah = $kelas->jumlahSiswaAktifPerSesi();
        $maks = $kelas->kuota_maksimal;
        $tabs = [];

        foreach ($sesi as $guru) {
            $terisi = (int) $jumlah->get($guru->id, 0);

            $tabs['sesi-' . $guru->id] = Tab::make($guru->nama)
                ->badge($maks !== null ? "{$terisi}/{$maks}" : (string) $terisi)
                ->badgeColor(match (true) {
                    $maks !== null && $terisi >= $maks => 'danger',
                    $kelas->statusKuota($terisi) === 'kurang' => 'warning',
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

    protected function bolehTambahSiswa(): bool
    {
        $kelas = $this->getOwnerRecord();
        $guruId = $this->sesiAktif();

        if ($guruId) {
            return ! $kelas->isKuotaSesiPenuh($guruId);
        }

        // Program tanpa sesi: kuota per program. Tab "Belum Memilih Sesi": tidak ada tombol.
        return $this->tabAktif() === 'semua' && ! $kelas->isKuotaPenuh();
    }

    protected function sudahDiProgram(mixed $siswaId): bool
    {
        return filled($siswaId) && KelasSiswa::query()
            ->where('kelas_id', $this->getOwnerRecord()->getKey())
            ->where('siswa_id', $siswaId)
            ->exists();
    }

    /** Siswa yang boleh ditambahkan di tab yang sedang dibuka. */
    protected function kandidatSiswa(): array
    {
        $kelas = $this->getOwnerRecord();
        $guruId = $this->sesiAktif();

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
                // Sesi: siswa yang sudah di program tetap boleh, asal belum di sesi ini
                fn ($q) => $q->whereNotIn('id', $kelas->subIdSiswaSesi($guruId)),
                // Tanpa sesi: yang sudah di program tidak ditampilkan
                fn ($q) => $q->whereNotIn('id', fn ($sub) => $sub->select('siswa_id')
                    ->from('kelas_siswa')
                    ->where('kelas_id', $kelas->getKey())),
            )
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /** Nama sesi (guru) yang sudah penuh dan akan diikuti siswa ini bila diaktifkan kembali. */
    protected function sesiPenuhUntukSiswa(Siswa $siswa): Collection
    {
        $kelas = $this->getOwnerRecord();

        // Program tanpa sesi: kuota per program seperti sebelumnya
        if ($kelas->sesiGuru()->isEmpty()) {
            return $kelas->isKuotaPenuh() ? collect(['program ini']) : collect();
        }

        return Guru::query()
            ->whereIn('id', Jadwal::query()
                ->where('kelas_id', $kelas->getKey())
                ->where('is_active', true)
                ->whereIn('id', fn ($q) => $q->select('jadwal_id')
                    ->from('jadwal_siswa')
                    ->where('siswa_id', $siswa->getKey()))
                ->select('guru_id'))
            ->get()
            ->filter(fn (Guru $guru) => $kelas->isKuotaSesiPenuh($guru->id))
            ->pluck('nama');
    }

    /** Jadwal (semua status) milik satu guru di program ini. */
    protected function idJadwalSesi(int $guruId): array
    {
        return Jadwal::query()
            ->where('kelas_id', $this->getOwnerRecord()->getKey())
            ->where('guru_id', $guruId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Sesi (guru) lain yang bisa jadi tujuan: bukan sesi ini dan belum diikuti siswa. */
    protected function sesiLain(Siswa $siswa): Collection
    {
        $kelas = $this->getOwnerRecord();

        $sudahDiikuti = Jadwal::query()
            ->where('kelas_id', $kelas->getKey())
            ->where('is_active', true)
            ->whereIn('id', fn ($q) => $q->select('jadwal_id')
                ->from('jadwal_siswa')
                ->where('siswa_id', $siswa->getKey()))
            ->pluck('guru_id');

        return $kelas->sesiGuru()->reject(
            fn (Guru $guru) => $guru->id === $this->sesiAktif() || $sudahDiikuti->contains($guru->id)
        );
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('tanggal_gabung')
                ->native(false)
                ->default(now()),

            Forms\Components\Toggle::make('is_active')
                ->label('Aktif di Program Ini')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $kelas = $this->getOwnerRecord();
                $guruId = $this->sesiAktif();

                if ($guruId) {
                    return $query->whereIn('siswa.id', $kelas->subIdSiswaSesi($guruId));
                }

                if ($this->tabAktif() === 'tanpa-sesi') {
                    return $query->whereNotIn('siswa.id', $kelas->subIdSiswaSesi());
                }

                return $query;
            })
            ->description(function () {
                $kelas = $this->getOwnerRecord();
                $guruId = $this->sesiAktif();

                if ($guruId) {
                    $terisi = $kelas->jumlahSiswaAktifSesi($guruId);
                    $kuota = $kelas->kuota_maksimal !== null
                        ? "{$terisi} / {$kelas->kuota_maksimal}"
                        : "{$terisi} (tanpa batas)";

                    return "Sesi {$this->namaSesiAktif()} · Siswa aktif: {$kuota} · Kuota minimal: {$kelas->kuota_minimal}";
                }

                if ($this->tabAktif() === 'tanpa-sesi') {
                    return 'Siswa program ini yang belum memilih jam di sesi (guru) mana pun. '
                        . 'Tambahkan lewat tab sesi, atau atur jamnya di halaman Siswa.';
                }

                $terisi = $kelas->jumlahSiswaAktif();
                $kuota = $kelas->kuota_maksimal !== null
                    ? "{$terisi} / {$kelas->kuota_maksimal}"
                    : "{$terisi} (tanpa batas)";

                return "Siswa aktif: {$kuota} · Kuota minimal: {$kelas->kuota_minimal}";
            })
            ->columns([
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'aktif',
                        'warning' => 'cuti',
                        'danger' => 'keluar',
                    ]),
                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Aktif di Program')
                    ->boolean(),
                Tables\Columns\TextColumn::make('pivot.tanggal_gabung')
                    ->label('Tanggal Gabung')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('jam_dipilih')
                    ->label('Jam')
                    ->getStateUsing(fn ($record) => PenjadwalanSiswa::labelTersimpan(
                        $record->getKey(),
                        $this->getOwnerRecord()->getKey(),
                        $this->sesiAktif(),
                    ))
                    ->badge()
                    ->placeholder('Belum memilih jam'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tambahSiswa')
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
                            ->live(),

                        // Hanya untuk siswa yang belum terdaftar di program ini
                        Forms\Components\DatePicker::make('tanggal_gabung')
                            ->native(false)
                            ->default(now())
                            ->visible(fn (Get $get) => ! $this->sudahDiProgram($get('siswa_id'))),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif di Program Ini')
                            ->default(true)
                            ->visible(fn (Get $get) => ! $this->sudahDiProgram($get('siswa_id'))),

                        Forms\Components\Select::make('jadwal_ids')
                            ->label('Pilih jam')
                            ->multiple()
                            ->options(fn () => PenjadwalanSiswa::opsiSesi(
                                $this->getOwnerRecord()->getKey(),
                                (int) $this->sesiAktif(),
                            ))
                            ->visible(fn () => $this->sesiAktif() !== null)
                            ->required()
                            ->helperText(fn () => 'Jam milik sesi ' . $this->namaSesiAktif() . '. Pilih satu atau lebih.')
                            ->rule(fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (blank($get('siswa_id'))) {
                                    return;
                                }

                                // Siswa baru yang dimasukkan dalam keadaan nonaktif tidak dihitung bentrok
                                if (! $this->sudahDiProgram($get('siswa_id')) && ! $get('is_active')) {
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

                        // Pengaman di sisi server: tombol yang disembunyikan saja tidak cukup
                        $penuh = $guruId ? $kelas->isKuotaSesiPenuh($guruId) : $kelas->isKuotaPenuh();

                        if ($penuh) {
                            Notification::make()
                                ->title('Kuota penuh')
                                ->body($guruId
                                    ? 'Kuota maksimal sesi ' . $this->namaSesiAktif() . ' sudah tercapai.'
                                    : 'Siswa tidak dapat ditambahkan karena kuota maksimal sudah tercapai.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        if (! array_key_exists($siswaId, $this->kandidatSiswa()) || ($guruId && $jadwalIds === [])) {
                            Notification::make()
                                ->title('Siswa tidak dapat ditambahkan')
                                ->body('Siswa tidak aktif, sudah terdaftar di sesi ini, atau jam belum dipilih.')
                                ->danger()
                                ->send();

                            $action->halt();
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

                        $this->tabsCache = null;

                        Notification::make()->title('Siswa berhasil ditambahkan')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Update / CRM')
                    ->modalHeading(fn ($record) => 'Update / CRM — ' . $record->nama)
                    ->modalWidth('lg')
                    // Nilai awal diambil dari pivot (status di program ini), bukan dari data siswa
                    ->fillForm(fn ($record): array => [
                        'aksi' => 'ubah_status',
                        'is_active' => (bool) $record->pivot->is_active,
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
                    ->before(function (array $data, $record, Tables\Actions\EditAction $action) {
                        // Follow-up tidak mengubah status, jadi tidak perlu pengecekan apa pun
                        if (($data['aksi'] ?? 'ubah_status') !== 'ubah_status') {
                            return;
                        }

                        $statusBaru = (bool) ($data['is_active'] ?? false);
                        $statusLama = (bool) $record->pivot->is_active;

                        // Status tidak berubah -> jangan dicatat sebagai riwayat status
                        if ($statusBaru === $statusLama) {
                            Notification::make()
                                ->title('Status tidak berubah')
                                ->body('Status siswa di program ini sama dengan sebelumnya. Untuk mencatat ajakan atau penolakan, pilih aksi "Catat follow-up".')
                                ->warning()
                                ->send();

                            $action->halt();
                        }

                        if ($statusBaru && ! $statusLama) {
                            $penuh = $this->sesiPenuhUntukSiswa($record);

                            if ($penuh->isNotEmpty()) {
                                Notification::make()
                                    ->title('Kuota penuh')
                                    ->body('Siswa tidak dapat diaktifkan kembali karena kuota sesi sudah tercapai: ' . $penuh->join(', ') . '.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }
                    })
                    ->using(function ($record, array $data) {
                        $statusDikembalikan = false;

                        $hasil = DB::transaction(function () use ($record, $data, &$statusDikembalikan) {
                            // Follow-up: hanya menulis ke tabel follow-up, status tidak disentuh
                            if (($data['aksi'] ?? 'ubah_status') === 'follow_up') {
                                $record->pivot->followUp()->create([
                                    'tanggal' => $data['tanggal_perubahan'],
                                    'hasil' => $data['hasil'],
                                    'keterangan' => $data['keterangan'] ?? null,
                                    'diubah_oleh' => auth()->id(),
                                ]);

                                return $record;
                            }

                            $record->pivot->tanggalPerubahan = $data['tanggal_perubahan'] ?? now()->toDateString();
                            $record->pivot->keteranganPerubahan = $data['keterangan'] ?? null;
                            $record->pivot->is_active = $data['is_active'];
                            $record->pivot->save();

                            if ($data['is_active']) {
                                // Program diaktifkan: siswa yang masih nonaktif atau berstatus cuti/keluar ikut diaktifkan
                                $statusNonaktif = in_array($record->status, Siswa::STATUS_NONAKTIF, true);

                                if (! $record->is_active || $statusNonaktif) {
                                    $record->is_active = true;

                                    if ($statusNonaktif) {
                                        $record->status = 'aktif';
                                        $statusDikembalikan = true;
                                    }

                                    $record->save();
                                }
                            } else {
                                // Program dinonaktifkan: jika tidak ada program aktif lain, nonaktifkan siswanya
                                $masihPunyaProgramAktif = $record->kelasList()
                                    ->wherePivot('is_active', true)
                                    ->where('kelas.id', '!=', $this->getOwnerRecord()->getKey())
                                    ->exists();

                                if (! $masihPunyaProgramAktif) {
                                    $record->is_active = false;
                                    $record->save();
                                }
                            }

                            return $record;
                        });

                        if ($statusDikembalikan) {
                            Notification::make()
                                ->title('Status siswa dikembalikan ke aktif')
                                ->body($hasil->nama . ' sebelumnya berstatus cuti/keluar, sehingga status siswa ikut diubah menjadi aktif.')
                                ->info()
                                ->send();
                        }

                        return $hasil;
                    }),

                Tables\Actions\Action::make('riwayat')
                    ->label('Riwayat')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Riwayat : ' . $record->nama)
                    ->modalContent(fn ($record) => view('filament.partials.riwayat-kelas-siswa', [
                        'riwayat' => $record->pivot->timeline(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Tables\Actions\Action::make('pindahSesi')
                    ->label('Pindah Sesi')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->visible(fn () => $this->sesiAktif() !== null
                        && $this->getOwnerRecord()->sesiGuru()->count() > 1)
                    ->modalHeading(fn ($record) => 'Pindah Sesi — ' . $record->nama)
                    ->modalDescription(fn () => 'Dari sesi ' . $this->namaSesiAktif()
                        . '. Seluruh jam siswa di sesi ini akan dilepas dan diganti dengan jam di sesi tujuan.')
                    ->modalSubmitActionLabel('Pindahkan')
                    ->form(fn ($record) => [
                        Forms\Components\Select::make('guru_tujuan')
                            ->label('Pindah ke sesi')
                            ->options(function () use ($record) {
                                $kelas = $this->getOwnerRecord();
                                $jumlah = $kelas->jumlahSiswaAktifPerSesi();
                                $maks = $kelas->kuota_maksimal;

                                return $this->sesiLain($record)->mapWithKeys(function (Guru $guru) use ($jumlah, $maks) {
                                    $terisi = (int) $jumlah->get($guru->id, 0);

                                    $label = $maks === null
                                        ? "{$guru->nama} ({$terisi} siswa)"
                                        : "{$guru->nama} ({$terisi}/{$maks})" . ($terisi >= $maks ? ' — penuh' : '');

                                    return [$guru->id => $label];
                                })->all();
                            })
                            // Siswa nonaktif tidak terhitung kuota, jadi sesi penuh pun boleh dipilih
                            ->disableOptionWhen(fn (string $value) => (bool) $record->pivot->is_active
                                && $this->getOwnerRecord()->isKuotaSesiPenuh((int) $value))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('jadwal_ids', [])),

                        Forms\Components\Select::make('jadwal_ids')
                            ->label('Pilih jam')
                            ->multiple()
                            ->options(fn (Get $get) => filled($get('guru_tujuan'))
                                ? PenjadwalanSiswa::opsiSesi($this->getOwnerRecord()->getKey(), (int) $get('guru_tujuan'))
                                : [])
                            ->required()
                            ->helperText('Jam milik sesi tujuan. Pilih satu atau lebih.')
                            ->rule(fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
                                // Siswa nonaktif di program ini tidak terhitung bentrok
                                if (! (bool) $record->pivot->is_active) {
                                    return;
                                }

                                $pesan = PenjadwalanSiswa::pesanBentrok(
                                    array_map('intval', (array) $value),
                                    (int) $record->getKey(),
                                    $this->idJadwalSesi((int) $this->sesiAktif()),
                                );

                                if ($pesan) {
                                    $fail($pesan);
                                }
                            }),
                    ])
                    ->action(function (array $data, $record, Tables\Actions\Action $action) {
                        $kelas = $this->getOwnerRecord();
                        $asal = $this->sesiAktif();
                        $tujuan = (int) ($data['guru_tujuan'] ?? 0);
                        $aktif = (bool) $record->pivot->is_active;

                        // Pengaman di sisi server: tujuan harus sesi lain yang valid
                        if (! $asal || ! $this->sesiLain($record)->contains('id', $tujuan)) {
                            Notification::make()
                                ->title('Sesi tujuan tidak valid')
                                ->body('Pilih sesi lain yang belum diikuti siswa ini.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        // Jam harus milik sesi tujuan
                        $jadwalTujuan = array_values(array_intersect(
                            array_map('intval', $data['jadwal_ids'] ?? []),
                            array_keys(PenjadwalanSiswa::opsiSesi($kelas->getKey(), $tujuan)),
                        ));

                        if ($jadwalTujuan === []) {
                            Notification::make()
                                ->title('Jam belum dipilih')
                                ->body('Pilih minimal satu jam milik sesi tujuan.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        // Kuota sesi tujuan (hanya berlaku untuk siswa yang aktif di program)
                        if ($aktif && $kelas->isKuotaSesiPenuh($tujuan)) {
                            Notification::make()
                                ->title('Kuota penuh')
                                ->body('Kuota maksimal sesi tujuan sudah tercapai.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        PenjadwalanSiswa::pindahSesi($record->getKey(), $kelas->getKey(), $asal, $jadwalTujuan);

                        $this->tabsCache = null;

                        Notification::make()
                            ->title($record->nama . ' dipindahkan ke sesi ' . (Guru::find($tujuan)?->nama ?? '-'))
                            ->success()
                            ->send();

                        // Sesi asal bisa jatuh di bawah kuota minimal setelah ditinggalkan
                        if ($aktif && $kelas->kuota_minimal > 0
                            && $kelas->jumlahSiswaAktifSesi($asal) < $kelas->kuota_minimal) {
                            Notification::make()
                                ->title('Sesi asal di bawah kuota minimal')
                                ->body('Siswa aktif sesi ' . $this->namaSesiAktif() . ' kini kurang dari kuota minimal (' . $kelas->kuota_minimal . ').')
                                ->warning()
                                ->send();
                        }
                    }),

                Tables\Actions\DetachAction::make()
                    ->label('Keluarkan')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalHeading(fn ($record) => 'Keluarkan ' . $record->nama . ' dari program ini?')
                    ->modalDescription(fn ($record) => $this->peringatanKeluarkan(
                        $record->pivot->riwayat->count(),
                        $record->pivot->followUp->count()
                    ))
                    ->modalSubmitActionLabel('Ya, keluarkan permanen')
                    ->before(function ($record, Tables\Actions\DetachAction $action) {
                        // Hitung semua program lain, aktif maupun nonaktif
                        $punyaProgramLain = $record->kelasList()
                            ->where('kelas.id', '!=', $this->getOwnerRecord()->getKey())
                            ->exists();

                        if (! $punyaProgramLain) {
                            Notification::make()
                                ->title('Siswa tidak dapat dikeluarkan')
                                ->body('Setiap siswa harus terdaftar minimal di satu program (aktif maupun nonaktif).')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Keluarkan yang dipilih')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalHeading(fn (Collection $records) => "Keluarkan {$records->count()} siswa dari program ini?")
                    ->modalDescription(fn (Collection $records) => $this->peringatanKeluarkan(
                        ...$this->hitungCatatan($records)
                    ))
                    ->modalSubmitActionLabel('Ya, keluarkan permanen')
                    ->before(function (Collection $records, Tables\Actions\DetachBulkAction $action) {
                        $kelasId = $this->getOwnerRecord()->getKey();

                        // Siswa yang tidak punya program lain (aktif maupun nonaktif)
                        $tanpaProgramLain = $records->filter(
                            fn ($siswa) => ! $siswa->kelasList()->where('kelas.id', '!=', $kelasId)->exists()
                        );

                        if ($tanpaProgramLain->isNotEmpty()) {
                            Notification::make()
                                ->title('Pengeluaran dibatalkan')
                                ->body(
                                    'Setiap siswa harus terdaftar minimal di satu program. '
                                    . 'Siswa berikut tidak punya program lain: '
                                    . $tanpaProgramLain->pluck('nama')->join(', ')
                                    . '. Tidak ada siswa yang dikeluarkan.'
                                )
                                ->danger()
                                ->persistent()
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }

    protected function hitungCatatan(Collection $records): array
    {
        // Ambil lewat relasi supaya data pivot ikut terisi
        $siswa = $this->getOwnerRecord()
            ->siswa()
            ->whereKey($records->modelKeys())
            ->get();

        return [
            $siswa->sum(fn ($s) => $s->pivot->riwayat->count()),
            $siswa->sum(fn ($s) => $s->pivot->followUp->count()),
        ];
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