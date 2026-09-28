<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    // Heading & logo bawaan dimatikan karena sudah kita tampilkan sendiri di view.
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function hasLogo(): bool
    {
        return false;
    }
}