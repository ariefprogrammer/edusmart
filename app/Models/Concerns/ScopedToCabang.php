<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopedToCabang
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin')) {
            $query->whereIn('cabang_id', $user->cabang->pluck('id'));
        }

        return $query;
    }
}