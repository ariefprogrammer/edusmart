<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaStatusRiwayat extends Model
{
    protected $table = 'siswa_status_riwayat';

    protected $fillable = [
        'siswa_id', 'status_lama', 'status_baru', 'tanggal_perubahan', 'keterangan', 'diubah_oleh',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}