<?php

namespace App\Models;

use App\Models\Concerns\FormatsIndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaKeluarga extends Model
{
    use FormatsIndonesianPhoneNumber;

    protected $table = 'siswa_keluarga';

    protected $fillable = [
        'siswa_id',
        'nama_ayah', 'nik_ayah', 'tempat_lahir_ayah', 'tanggal_lahir_ayah',
        'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah', 'media_sosial_ayah',
        'nama_ibu', 'nik_ibu', 'tempat_lahir_ibu', 'tanggal_lahir_ibu',
        'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu', 'media_sosial_ibu',
        'nama_wali', 'nik_wali', 'tahun_lahir_wali', 'alamat_wali',
        'telepon_wali', 'seluler_wali', 'pendidikan_wali', 'pekerjaan_wali',
        'penghasilan_wali',
    ];

    protected $casts = [
        'tanggal_lahir_ayah' => 'date',
        'tanggal_lahir_ibu' => 'date',
        'tahun_lahir_wali' => 'integer',
    ];

    protected function phoneNumberFields(): array
    {
        return ['telepon_wali', 'seluler_wali'];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}