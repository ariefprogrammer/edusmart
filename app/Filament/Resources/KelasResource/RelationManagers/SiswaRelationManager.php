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

class SiswaRelationManager extends RelationManager
{
    protected static string $relationship = 'siswa';
    protected static ?string $inverseRelationship = 'kelasList';
    protected static ?string $recordTitleAttribute = 'nama';
    protected static ?string $title = 'Siswa Terdaftar';

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
            ->description(function () {
                $kelas = $this->getOwnerRecord();
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
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label(fn () => $this->getOwnerRecord()->isKuotaPenuh()
                        ? 'Kuota Penuh'
                        : __('filament-actions::attach.single.label'))
                    ->icon(fn () => $this->getOwnerRecord()->isKuotaPenuh()
                        ? 'heroicon-o-lock-closed'
                        : 'heroicon-o-plus')
                    ->color(fn () => $this->getOwnerRecord()->isKuotaPenuh() ? 'danger' : 'primary')
                    ->disabled(fn () => $this->getOwnerRecord()->isKuotaPenuh())
                    ->tooltip(fn () => $this->getOwnerRecord()->isKuotaPenuh()
                        ? 'Kuota maksimal sudah tercapai'
                        : null)
                    ->before(function (Tables\Actions\AttachAction $action) {
                        // Pengaman di sisi server: tombol nonaktif saja tidak cukup
                        if ($this->getOwnerRecord()->isKuotaPenuh()) {
                            Notification::make()
                                ->title('Kuota penuh')
                                ->body('Siswa tidak dapat ditambahkan karena kuota maksimal sudah tercapai.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['nama'])
                    ->recordSelectOptionsQuery(
                        fn ($query) => $query->where('cabang_id', $this->getOwnerRecord()->cabang_id)
                    )
                    ->form(fn (Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect(),
                        Forms\Components\DatePicker::make('tanggal_gabung')
                            ->native(false)
                            ->default(now()),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif di Program Ini')
                            ->default(true),
                    ]),
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

                        if ($statusBaru && ! $statusLama && $this->getOwnerRecord()->isKuotaPenuh()) {
                            Notification::make()
                                ->title('Kuota penuh')
                                ->body('Siswa tidak dapat diaktifkan kembali karena kuota maksimal sudah tercapai.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->using(function ($record, array $data) {
                        return DB::transaction(function () use ($record, $data) {
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

                            $record->pivot->tanggalPerubahan = $data['tanggal_perubahan'] ?? now();
                            $record->pivot->keteranganPerubahan = $data['keterangan'] ?? null;
                            $record->pivot->is_active = $data['is_active'];
                            $record->pivot->save();

                            if ($data['is_active']) {
                                // Program diaktifkan: jika siswa masih nonaktif, aktifkan juga siswanya
                                if (! $record->is_active) {
                                    $record->is_active = true;
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
            . "<li>{$jumlahRiwayat} catatan riwayat status dan {$jumlahFollowUp} catatan follow-up di program ini akan <strong>dihapus permanen</strong>.</li>"
            . '<li>Tanggal gabung hilang dan tindakan ini <strong>tidak bisa dibatalkan</strong>.</li>'
            . '<li>Siswa yang dikeluarkan tidak akan lagi terhitung di laporan periode lalu untuk program ini.</li>'
            . '</ul>'
        );
    }
}