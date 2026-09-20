<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasSiswaDetail extends Model
{
    protected $table = 'kelas_siswa_detail';

    protected $fillable = ['kelas_siswa_id', 'status', 'tanggal', 'keterangan', 'diubah_oleh'];

    protected $casts = ['tanggal' => 'date'];

    public function kelasSiswa(): BelongsTo
    {
        return $this->belongsTo(KelasSiswa::class);
    }

    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}