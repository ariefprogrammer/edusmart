<?php

namespace App\Models;

use App\Models\Concerns\FormatsIndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\HasUuid;
use App\Models\KelasSiswa;

class Siswa extends Model
{
    use SoftDeletes, FormatsIndonesianPhoneNumber, HasUuid;

    protected $table = 'siswa';

    protected $fillable = [
        'uuid', 'cabang_id', 'wali_murid_id', 'nama', 'tanggal_lahir',
        'alamat', 'telepon', 'status', 'tanggal_daftar', 'is_active',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_daftar' => 'date',
    ];

    protected function phoneNumberFields(): array
    {
        return ['telepon'];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function waliMurid(): BelongsTo
    {
        return $this->belongsTo(WaliMurid::class, 'wali_murid_id');
    }

    public function statusRiwayat(): HasMany
    {
        return $this->hasMany(SiswaStatusRiwayat::class);
    }

    public function kelasList(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_siswa')
            ->using(KelasSiswa::class)
            ->withPivot(['id', 'tanggal_gabung', 'is_active'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::created(function (Siswa $siswa) {
            $siswa->statusRiwayat()->create([
                'status_lama' => null,
                'status_baru' => $siswa->status,
                'tanggal_perubahan' => now(),
                'diubah_oleh' => auth()->id(),
            ]);
        });

        static::updating(function (Siswa $siswa) {
            if ($siswa->isDirty('status')) {
                $siswa->statusRiwayat()->create([
                    'status_lama' => $siswa->getOriginal('status'),
                    'status_baru' => $siswa->status,
                    'tanggal_perubahan' => now(),
                    'diubah_oleh' => auth()->id(),
                ]);
            }
        });
    }

}