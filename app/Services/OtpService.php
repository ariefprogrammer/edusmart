<?php

namespace App\Services;

use App\Exceptions\OtpThrottledException;
use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function requiresOtp(User $user): bool
    {
        if (! config('otp.enabled', true)) {
            return false;
        }
        
        return $user->hasAnyRole(config('otp.required_roles', []));
    }

    /** @throws OtpThrottledException */
    public function issue(User $user, ?string $ip = null, ?string $userAgent = null): LoginOtp
    {
        $this->guardThrottle($user);

        $code = $this->generateCode();
        $minutes = (int) config('otp.expires_in_minutes', 10);

        $otp = DB::transaction(function () use ($user, $code, $minutes, $ip, $userAgent) {
            // Kode lama otomatis tidak valid
            LoginOtp::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return LoginOtp::create([
                'user_id' => $user->id,
                'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes($minutes),
                'ip_address' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            ]);
        });

        Mail::to($user->email)->queue(new LoginOtpMail($user->name, $code, $minutes));

        return $otp;
    }

    public function verify(User $user, string $code): bool
    {
        $max = (int) config('otp.max_attempts', 5);

        return DB::transaction(function () use ($user, $code, $max) {
            $otp = LoginOtp::where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                return false;
            }

            if ($otp->attempts >= $max) {
                $otp->update(['used_at' => now()]);
                return false;
            }

            $otp->increment('attempts');

            if (! hash_equals($otp->code_hash, $this->hash($code))) {
                if ($otp->attempts >= $max) {
                    $otp->update(['used_at' => now()]); // hangus, harus login ulang
                }
                return false;
            }

            $otp->update(['used_at' => now()]);
            return true;
        });
    }

    /** Sisa detik sebelum boleh kirim ulang (0 = boleh sekarang). */
    public function secondsUntilResend(User $user): int
    {
        $latest = LoginOtp::where('user_id', $user->id)->latest('id')->first();

        if (! $latest) {
            return 0;
        }

        $availableAt = $latest->created_at
            ->copy()
            ->addSeconds((int) config('otp.resend_cooldown_seconds', 60));

        return max(0, $availableAt->timestamp - now()->timestamp);
    }

    protected function guardThrottle(User $user): void
    {
        // 1) Cooldown antar kiriman
        $wait = $this->secondsUntilResend($user);
        if ($wait > 0) {
            throw new OtpThrottledException($wait);
        }

        // 2) Batas per jam
        $maxPerHour = (int) config('otp.max_sends_per_hour', 5);

        $recent = LoginOtp::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->orderBy('created_at')
            ->get(['created_at']);

        if ($recent->count() >= $maxPerHour) {
            $retryAt = $recent->first()->created_at->copy()->addHour();
            throw new OtpThrottledException(max(1, $retryAt->timestamp - now()->timestamp));
        }
    }

    protected function generateCode(): string
    {
        $length = (int) config('otp.code_length', 6);

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    // HMAC dengan APP_KEY: cepat, dan tidak bisa dibruteforce tanpa key
    protected function hash(string $code): string
    {
        return hash_hmac('sha256', trim($code), (string) config('app.key'));
    }
}