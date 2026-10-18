<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtp extends Model
{
    use Prunable;

    protected $fillable = [
        'user_id', 'code_hash', 'expires_at', 'attempts',
        'used_at', 'ip_address', 'user_agent',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Dibersihkan oleh `php artisan model:prune`
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(7));
    }
}