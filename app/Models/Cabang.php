<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FormatsIndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabang extends Model
{
    /** @use HasFactory<\Database\Factories\CabangFactory> */
    use HasFactory;

    protected $fillable = [
        'nama_cabang', 'alamat', 'pic_nama', 'pic_telepon', 'jam_buka', 'jam_tutup', 'is_active',
        'latitude', 'longitude', 'radius_presensi_meter', 'toleransi_keterlambatan_menit',
    ];

    protected function phoneNumberFields(): array
    {
        return ['pic_telepon'];
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function guru(): HasMany
    {
        return $this->hasMany(Guru::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cabang_user');
    }

    public function periode(): HasMany
    {
        return $this->hasMany(Periode::class);
    }

    public function distanceInMeters(float $lat, float $lng): ?float
    {
        if (is_null($this->latitude) || is_null($this->longitude)) {
            return null;
        }

        $earthRadius = 6371000;

        $dLat = deg2rad($lat - (float) $this->latitude);
        $dLng = deg2rad($lng - (float) $this->longitude);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
