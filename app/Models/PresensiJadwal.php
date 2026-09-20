<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiJadwal extends Model
{
    protected $table = 'presensi_jadwal';

    protected $fillable = [
        'cabang_id', 'jadwal_id', 'guru_id', 'tanggal',
        'check_in', 'check_in_lat', 'check_in_lng', 'check_in_accuracy',
        'check_out', 'check_out_lat', 'check_out_lng', 'check_out_accuracy',
        'status_masuk','status_keluar',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}