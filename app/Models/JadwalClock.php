<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalClock extends Model
{
    protected $table = 'jadwal_clock';

    protected $fillable = ['cabang_id', 'user_id', 'hari', 'clock_in', 'clock_out', 'is_active'];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}