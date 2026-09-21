<?php

namespace App\Filament\Resources\KelasResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->using(function ($record, array $data) {
                        $record->pivot->tanggalPerubahan = $data['tanggal_perubahan'] ?? now();
                        $record->pivot->keteranganPerubahan = $data['keterangan'] ?? null;
                        $record->pivot->is_active = $data['is_active'];
                        $record->pivot->save();

                        return $record;
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
                    ->label('Keluarkan'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}