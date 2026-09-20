<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Jadwal extends Model
{
    protected $table = 'jadwal';

    protected $fillable = [
        'cabang_id', 'kelas_id', 'guru_id', 'hari', 'jam_mulai', 'jam_selesai', 'is_active',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiJadwal::class);
    }
}