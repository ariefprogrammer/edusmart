<?php

namespace App\Filament\Pages;

use App\Models\TrustedDevice;
use App\Services\OtpService;
use App\Services\TrustedDeviceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class TrustedDevices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Perangkat Tepercaya';
    protected static ?string $navigationGroup = 'Akun';
    protected static ?string $title = 'Perangkat Tepercaya';
    protected static string $view = 'filament.pages.trusted-devices';

    // Menu hanya tampil untuk role yang memang kena OTP
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user && app(OtpService::class)->requiresOtp($user);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TrustedDevice::query()->where('user_id', auth()->id()))
            ->columns([
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Perangkat')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Terakhir dipakai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Berlaku sampai')
                    ->dateTime('d M Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Cabut')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (TrustedDevice $record) => $record->delete()),
            ])
            ->defaultSort('last_used_at', 'desc')
            ->emptyStateHeading('Belum ada perangkat tepercaya');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revokeAll')
                ->label('Cabut semua')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    app(TrustedDeviceService::class)->revokeAll(auth()->user());

                    Notification::make()
                        ->title('Semua perangkat tepercaya dicabut')
                        ->success()
                        ->send();
                }),
        ];
    }
}