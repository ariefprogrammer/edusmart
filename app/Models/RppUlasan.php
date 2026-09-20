<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RppUlasan extends Model
{
    protected $table = 'rpp_ulasan';

    protected $fillable = ['rpp_id', 'user_id', 'ulasan'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}