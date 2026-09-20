<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rpp extends Model
{
    protected $table = 'rpp';

    protected $fillable = ['cabang_id', 'kelas_id', 'guru_id', 'periode_id', 'judul', 'file_path'];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function ulasan(): HasMany
    {
        return $this->hasMany(RppUlasan::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}