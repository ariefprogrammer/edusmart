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
        'kuota_minimal', 'kuota_maksimal',
    ];

    protected $casts = [
        'kuota_minimal' => 'integer',
        'kuota_maksimal' => 'integer',
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

    public function jumlahSiswaAktif(): int
    {
        return $this->siswa()->wherePivot('is_active', true)->count();
    }

    public function isKuotaPenuh(): bool
    {
        // Kosong = tanpa batas, jadi tidak pernah penuh
        return $this->kuota_maksimal !== null
            && $this->jumlahSiswaAktif() >= $this->kuota_maksimal;
    }

    public static function idPenuh(?array $ids = null): array
    {
        return static::query()
            ->whereNotNull('kuota_maksimal')
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->withCount([
                'siswa as siswa_aktif_count' => fn ($query) => $query->where('kelas_siswa.is_active', true),
            ])
            ->get()
            ->filter(fn (Kelas $kelas) => $kelas->siswa_aktif_count >= $kelas->kuota_maksimal)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function belumMemenuhiMinimal(?array $ids = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('kuota_minimal', '>', 0)
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->withCount([
                'siswa as siswa_aktif_count' => fn ($query) => $query->where('kelas_siswa.is_active', true),
            ])
            ->get()
            ->filter(fn (Kelas $kelas) => $kelas->siswa_aktif_count < $kelas->kuota_minimal)
            ->keyBy('id');
    }

    public function statusKuota(?int $jumlah = null): string
    {
        $jumlah ??= $this->siswa_aktif_count ?? $this->jumlahSiswaAktif();

        if ($this->kuota_minimal > 0 && $jumlah < $this->kuota_minimal) {
            return 'kurang';
        }

        if ($this->kuota_maksimal !== null && $jumlah > $this->kuota_maksimal) {
            return 'lebih';
        }

        return 'ok';
    }
}