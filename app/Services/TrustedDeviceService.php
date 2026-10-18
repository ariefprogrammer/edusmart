<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public function cookieName(): string
    {
        return config('otp.trusted_device_cookie', 'trusted_device');
    }

    /** Masa berlaku (hari): ambil yang terpendek di antara role user. */
    public function days(User $user): int
    {
        $days = (int) config('otp.trusted_device_days', 30);
        $byRole = config('otp.trusted_device_days_by_role', []);

        foreach ($user->getRoleNames() as $role) {
            if (isset($byRole[$role])) {
                $days = min($days, (int) $byRole[$role]);
            }
        }

        return $days;
    }

    /** Apakah browser ini membawa cookie perangkat tepercaya milik user ini? */
    public function isTrusted(User $user, Request $request): bool
    {
        $token = $request->cookie($this->cookieName());

        if (! is_string($token) || $token === '') {
            return false;
        }

        $device = TrustedDevice::where('user_id', $user->id)
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return false;
        }

        $device->update(['last_used_at' => now()]);

        return true;
    }

    /** Buat perangkat tepercaya baru dan kirim cookie-nya. */
    public function remember(User $user, Request $request): void
    {
        // Hindari baris dobel kalau browser ini sudah punya cookie lama
        $old = $request->cookie($this->cookieName());
        if (is_string($old) && $old !== '') {
            TrustedDevice::where('user_id', $user->id)
                ->where('token_hash', $this->hash($old))
                ->delete();
        }

        $days = $this->days($user);
        $token = Str::random(64);

        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'device_name' => $this->describeDevice((string) $request->userAgent()),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        Cookie::queue(Cookie::make(
            name: $this->cookieName(),
            value: $token,
            minutes: $days * 24 * 60,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));
    }

    public function revokeAll(User $user): void
    {
        TrustedDevice::where('user_id', $user->id)->delete();
    }

    protected function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    protected function describeDevice(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'perangkat tidak dikenal',
        };

        return "{$browser} di {$os}";
    }
}