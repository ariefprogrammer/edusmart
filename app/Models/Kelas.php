<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\KelasSiswa;

class Kelas extends Model
{
    protected $table = 'kelas';

    protected $fillable = [
        'cabang_id', 'nama_kelas', 'keterangan', 'is_active',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'kelas_siswa')
            ->using(KelasSiswa::class)
            ->withPivot(['id', 'tanggal_gabung', 'is_active'])
            ->withTimestamps();
    }
}