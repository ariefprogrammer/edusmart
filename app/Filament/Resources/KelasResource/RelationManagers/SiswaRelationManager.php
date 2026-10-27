<?php

namespace App\Filament\Resources\KelasResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

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
                    ->modalHeading(fn ($record) => 'Ubah Status — ' . $record->nama)
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Aktif di Program Ini')
                                    ->helperText('Nonaktifkan jika siswa berhenti atau keluar dari program ini.')
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\DatePicker::make('tanggal_perubahan')
                                    ->label('Tanggal')
                                    ->native(false)
                                    ->default(now())
                                    ->required(),

                                Forms\Components\Textarea::make('keterangan')
                                    ->label('Keterangan / Alasan')
                                    ->placeholder('Contoh: pindah ke kelas lain, mengundurkan diri, dll.')
                                    ->rows(3),
                            ])
                            ->columns(1),
                    ])
                    ->before(function (array $data, $record, Tables\Actions\EditAction $action) {
                        $mengaktifkanLagi = ($data['is_active'] ?? false) && ! $record->pivot->is_active;

                        if ($mengaktifkanLagi && $this->getOwnerRecord()->isKuotaPenuh()) {
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
                        'riwayat' => $record->pivot->riwayat,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Tables\Actions\DetachAction::make()
                    ->label('Keluarkan')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalHeading(fn ($record) => 'Keluarkan ' . $record->nama . ' dari program ini?')
                    ->modalDescription(function ($record) {
                        $jumlahRiwayat = $record->pivot->riwayat->count();

                        return new HtmlString(
                            '<p><strong>Fitur ini hanya untuk koreksi salah input.</strong> '
                            . 'Jika siswa berhenti atau keluar, gunakan tombol <em>Update / CRM</em> agar riwayatnya tetap tersimpan.</p>'
                            . '<p class="mt-2">Jika dilanjutkan:</p>'
                            . '<ul class="list-disc ms-5 mt-1">'
                            . "<li>{$jumlahRiwayat} catatan riwayat siswa di program ini akan <strong>dihapus permanen</strong>.</li>"
                            . '<li>Tanggal gabung hilang dan tindakan ini <strong>tidak bisa dibatalkan</strong>.</li>'
                            . '<li>Siswa ini tidak akan lagi terhitung di laporan periode lalu untuk program ini.</li>'
                            . '</ul>'
                        );
                    })
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
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}