<?php

namespace App\Filament\Pages\Auth;

use App\Exceptions\OtpThrottledException;
use App\Models\User;
use App\Services\OtpService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    // Penanda tampilan saja. Data sensitif (ID user) ada di session.
    #[Locked]
    public bool $awaitingOtp = false;

    #[Locked]
    public string $maskedEmail = '';

    #[Locked]
    public int $resendSeconds = 0;

    public ?array $otpData = [];

    // Heading & logo bawaan dimatikan karena sudah kita tampilkan sendiri di view.
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function mount(): void
    {
        parent::mount();

        $this->otpForm->fill();

        // Kalau halaman di-refresh saat menunggu OTP, lanjutkan langkahnya
        if ($pending = $this->getPending()) {
            $user = User::find($pending['user_id']);

            if ($user) {
                $this->awaitingOtp = true;
                $this->maskedEmail = $pending['masked_email'];
                $this->resendSeconds = app(OtpService::class)->secondsUntilResend($user);
            }
        }
    }

    protected function getForms(): array
    {
        return array_merge(parent::getForms(), [
            'otpForm' => $this->makeForm()
                ->schema([
                    TextInput::make('code')
                        ->label('Kode verifikasi')
                        ->required()
                        ->rule('digits:' . config('otp.code_length', 6))
                        ->autocomplete('one-time-code')
                        ->autofocus()
                        ->extraInputAttributes([
                            'inputmode' => 'numeric',
                            'maxlength' => config('otp.code_length', 6),
                            'class' => 'text-center tracking-widest',
                        ]),
                ])
                ->statePath('otpData'),
        ]);
    }

    /**
     * Langkah 1: cek email + password TANPA membuat sesi login.
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = (bool) ($data['remember'] ?? false);

        $provider = Filament::auth()->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            $this->throwFailureValidationException();
        }

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            $this->throwFailureValidationException();
        }

        $otp = app(OtpService::class);

        // Role di luar daftar (wali, siswa): login seperti biasa
        if (! $otp->requiresOtp($user)) {
            return $this->completeLogin($user, $remember);
        }

        // LANGKAH 4 nanti: cek cookie trusted device di sini

        return $this->startOtpChallenge($user, $remember, $otp);
    }

    /**
     * Langkah 2: verifikasi kode OTP.
     */
    public function verifyOtp(): ?LoginResponse
    {
        try {
            $this->rateLimit(10);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $pending = $this->getPending();

        if (! $pending) {
            $this->resetOtpState();

            throw ValidationException::withMessages([
                'data.email' => 'Sesi verifikasi berakhir. Silakan login ulang.',
            ]);
        }

        $data = $this->otpForm->getState();
        $user = User::find($pending['user_id']);

        if (! $user || ! app(OtpService::class)->verify($user, (string) $data['code'])) {
            throw ValidationException::withMessages([
                'otpData.code' => 'Kode salah atau sudah kedaluwarsa.',
            ]);
        }

        session()->forget('otp_pending');

        // LANGKAH 4 nanti: buat trusted device di sini bila dicentang

        return $this->completeLogin($user, (bool) $pending['remember']);
    }

    public function resendOtp(): void
    {
        $pending = $this->getPending();
        $user = $pending ? User::find($pending['user_id']) : null;

        if (! $user) {
            $this->resetOtpState();

            return;
        }

        try {
            app(OtpService::class)->issue($user, request()->ip(), request()->userAgent());

            $this->resendSeconds = (int) config('otp.resend_cooldown_seconds', 60);
            $this->dispatch('otp-resent', seconds: $this->resendSeconds);

            Notification::make()->title('Kode baru telah dikirim')->success()->send();
        } catch (OtpThrottledException $e) {
            $this->resendSeconds = $e->retryAfterSeconds;
            $this->dispatch('otp-resent', seconds: $e->retryAfterSeconds);

            Notification::make()
                ->title('Terlalu sering meminta kode')
                ->body("Coba lagi dalam {$e->retryAfterSeconds} detik.")
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Gagal mengirim kode verifikasi')
                ->body('Coba lagi beberapa saat.')
                ->danger()
                ->send();
        }
    }

    public function backToLogin(): void
    {
        $this->resetOtpState();
    }

    protected function startOtpChallenge(User $user, bool $remember, OtpService $otp): ?LoginResponse
    {
        try {
            $otp->issue($user, request()->ip(), request()->userAgent());
        } catch (OtpThrottledException $e) {
            // Tunggu pendek = baru saja minta kode, kode lama masih bisa dipakai.
            // Tunggu panjang = kena batas per jam, tidak ada kode aktif.
            if ($e->retryAfterSeconds > (int) config('otp.resend_cooldown_seconds', 60)) {
                Notification::make()
                    ->title('Terlalu banyak permintaan kode')
                    ->body('Silakan coba lagi nanti.')
                    ->danger()
                    ->send();

                return null;
            }

            Notification::make()
                ->title('Kode sudah dikirim sebelumnya')
                ->body('Gunakan kode di email Anda.')
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Gagal mengirim kode verifikasi')
                ->body('Coba lagi beberapa saat.')
                ->danger()
                ->send();

            return null;
        }

        session()->put('otp_pending', [
            'user_id' => $user->id,
            'remember' => $remember,
            'masked_email' => $this->maskEmail($user->email),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $this->awaitingOtp = true;
        $this->maskedEmail = $this->maskEmail($user->email);
        $this->resendSeconds = $otp->secondsUntilResend($user);
        $this->data['password'] = null; // jangan biarkan password tertinggal di state
        $this->otpForm->fill();

        return null;
    }

    protected function completeLogin(User $user, bool $remember): LoginResponse
    {
        Filament::auth()->login($user, $remember);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function getPending(): ?array
    {
        $pending = session('otp_pending');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('otp_pending');

            return null;
        }

        return $pending;
    }

    protected function resetOtpState(): void
    {
        session()->forget('otp_pending');

        $this->awaitingOtp = false;
        $this->maskedEmail = '';
        $this->resendSeconds = 0;
        $this->otpForm->fill();
    }

    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2)
            . str_repeat('*', max(1, strlen($local) - 2))
            . '@' . $domain;
    }
}