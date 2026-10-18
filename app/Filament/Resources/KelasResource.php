<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KelasResource\Pages;
use App\Filament\Resources\KelasResource\RelationManagers;
use App\Models\Kelas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use App\Models\Concerns\ScopedToCabang;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\Alignment;
use App\Filament\Resources\KelasResource\RelationManagers\SiswaRelationManager;

class KelasResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = Kelas::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 6;

    // Menu
    protected static ?string $navigationLabel = 'Kelas / Program';

    // Label
    protected static ?string $pluralModelLabel = 'Kelas / Program';

    // Tombol create
    protected static ?string $modelLabel = 'Kelas / Program';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cabang_id')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->cabang->first()?->id)
                    ->dehydrated(),

                Forms\Components\TextInput::make('nama_kelas')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Forms\Components\TextInput::make('kuota_minimal')
                    ->label('Kuota Minimal')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->default(0)
                    ->required()
                    ->helperText('Jumlah siswa minimal agar kelas dijalankan.')
                    ->validationMessages([
                        'required' => 'Kuota minimal wajib diisi.',
                        'numeric' => 'Kuota minimal harus berupa angka.',
                        'integer' => 'Kuota minimal harus berupa bilangan bulat.',
                        'min' => 'Kuota minimal tidak boleh kurang dari 0.',
                        'max' => 'Kuota minimal tidak boleh lebih dari 65535.',
                    ]),

                Forms\Components\TextInput::make('kuota_maksimal')
                    ->label('Kuota Maksimal')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->gte('kuota_minimal')
                    ->helperText('Jumlah siswa maksimal. Kosongkan jika tidak dibatasi.')
                    ->validationMessages([
                        'numeric' => 'Kuota maksimal harus berupa angka.',
                        'integer' => 'Kuota maksimal harus berupa bilangan bulat.',
                        'min' => 'Kuota maksimal minimal 1.',
                        'max' => 'Kuota maksimal tidak boleh lebih dari 65535.',
                        'gte' => 'Kuota maksimal tidak boleh lebih kecil dari kuota minimal.',
                    ]),

                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_kelas')
                    ->label('Nama Kelas / Program')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kuota_minimal')
                    ->label('Kuota Min')
                    ->alignment(Alignment::Center),
                Tables\Columns\TextColumn::make('kuota_maksimal')
                    ->label('Kuota Maks')
                    ->placeholder('Tanpa batas')
                    ->alignment(Alignment::Center),
                Tables\Columns\IconColumn::make('is_active')
                    ->alignment(Alignment::Center)
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('kelola')
                    ->label('Kelola Program')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Kelas $record) => \App\Filament\Pages\ManajemenProgram::getUrl(['kelas' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SiswaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKelas::route('/'),
            'create' => Pages\CreateKelas::route('/create'),
            'edit' => Pages\EditKelas::route('/{record}/edit'),
        ];
    }
}
