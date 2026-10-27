<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasSiswaFollowUp extends Model
{
    protected $table = 'kelas_siswa_follow_up';

    public const HASIL = [
        'bersedia' => 'Bersedia join kembali',
        'menolak' => 'Menolak',
        'belum_bisa_dihubungi' => 'Belum bisa dihubungi',
        'tidak_ada_respons' => 'Tidak ada respons',
    ];

    protected $fillable = ['kelas_siswa_id', 'tanggal', 'hasil', 'keterangan', 'diubah_oleh'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function kelasSiswa(): BelongsTo
    {
        return $this->belongsTo(KelasSiswa::class, 'kelas_siswa_id');
    }

    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'diubah_oleh');
    }
}