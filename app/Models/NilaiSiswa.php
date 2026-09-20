<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends Model
{
    protected $table = 'nilai_siswa';

    protected $fillable = ['cabang_id', 'kelas_id', 'siswa_id', 'guru_id', 'kategori_nilai_id', 'periode_id', 'nilai'];

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

    public function kategoriNilai(): BelongsTo
    {
        return $this->belongsTo(KategoriNilai::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}