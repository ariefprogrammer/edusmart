<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Hari
{
    protected const MAP = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    public static function ini(): string
    {
        return static::dari(Carbon::now());
    }

    public static function dari(Carbon $tanggal): string
    {
        return static::MAP[$tanggal->dayOfWeek];
    }
}