<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiSiswa extends Model
{
    protected $table = 'presensi_siswa';

    protected $fillable = [
        'cabang_id', 'kelas_id', 'siswa_id', 'guru_id', 'tanggal', 'periode_id', 'status', 'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}