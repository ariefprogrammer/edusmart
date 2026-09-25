<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopedToCabang
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::isCabangRestricted()) {
            $query->whereIn('cabang_id', static::getScopedCabangIds());
        }

        return $query;
    }

    public static function isCabangRestricted(): bool
    {
        $user = auth()->user();

        return $user && ! $user->hasRole('super_admin');
    }

    public static function getScopedCabangIds(): array
    {
        return auth()->user()?->cabang->pluck('id')->toArray() ?? [];
    }
}