<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriNilai extends Model
{
    protected $table = 'kategori_nilai';

    protected $fillable = ['kelas_id', 'nama_kategori', 'urutan'];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}