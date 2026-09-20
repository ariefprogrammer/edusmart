<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PeriodeResource\Pages;
use App\Models\Concerns\ScopedToCabang;
use App\Models\Periode;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;

class PeriodeResource extends Resource
{

    protected static ?string $model = Periode::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cabang_id')
                ->relationship('cabang', 'nama_cabang')
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->default(fn () => auth()->user()->cabang->first()?->id)
                ->dehydrated(),

            TextInput::make('nama_periode')
                ->required()
                ->columnSpanFull()
                ->maxLength(255),

            Select::make('tipe')
                ->label('Tipe')
                ->columnSpanFull()
                ->options([
                    'bulan' => 'Bulan',
                    'semester' => 'Semester',
                ])
                ->default('bulan')
                ->required(),

            DatePicker::make('tanggal_mulai')
                ->native(false)
                ->columnSpanFull()
                ->required(),

            DatePicker::make('tanggal_selesai')
                ->native(false)
                ->columnSpanFull()
                ->required()
                ->after('tanggal_mulai'),
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

                Tables\Columns\TextColumn::make('nama_periode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('tipe')
                    ->colors([
                        'info' => 'bulan',
                        'success' => 'semester',
                    ])
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('tanggal_mulai')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_selesai')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                Tables\Filters\SelectFilter::make('tipe')
                    ->options([
                        'bulan' => 'Bulan',
                        'semester' => 'Semester',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin')) {
            $query->whereIn('cabang_id', $user->cabang->pluck('id'));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeriodes::route('/'),
            'create' => Pages\CreatePeriode::route('/create'),
            'edit' => Pages\EditPeriode::route('/{record}/edit'),
        ];
    }
}