<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait FormatsIndonesianPhoneNumber
{
    public static function bootFormatsIndonesianPhoneNumber(): void
    {
        static::saving(function ($model) {
            foreach ($model->phoneNumberFields() as $field) {
                if (filled($model->{$field})) {
                    $model->{$field} = static::normalizeIndonesianPhoneNumber($model->{$field});
                }
            }
        });
    }

    public static function normalizeIndonesianPhoneNumber(string $value): string
    {
        // buang semua karakter selain angka (spasi, strip, +, dll)
        $digits = preg_replace('/\D/', '', $value);

        if (Str::startsWith($digits, '0')) {
            // 08123456456 -> 628123456456
            $digits = '62' . substr($digits, 1);
        } elseif (! Str::startsWith($digits, '62')) {
            // 8123456456 -> 628123456456
            $digits = '62' . $digits;
        }
        // kalau sudah diawali 62, dibiarkan apa adanya

        return $digits;
    }
}