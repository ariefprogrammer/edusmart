<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiKaryawan extends Model
{
    protected $table = 'presensi_karyawan';

    protected $fillable = [
        'cabang_id', 'user_id', 'tanggal',
        'check_in', 'check_in_lat', 'check_in_lng', 'check_in_accuracy', 'check_in_foto',
        'check_out', 'check_out_lat', 'check_out_lng', 'check_out_accuracy', 'check_out_foto',
        'status_masuk', 'status_keluar', 'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}