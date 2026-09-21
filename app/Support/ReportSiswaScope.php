<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aturan akses data untuk Report Siswa. Dipakai oleh Resource (opsi form, daftar)
 * dan ReportSiswaService (validasi saat generate) supaya aturannya satu tempat.
 * Mengikuti pola LaporanPresensiSiswa: super_admin lihat semua, staf cabang lihat
 * cabangnya, guru hanya siswa dari program yang dia ajar.
 */
class ReportSiswaScope
{
    public static function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public static function isGuruOnly(User $user): bool
    {
        return $user->hasRole('guru')
            && ! $user->hasAnyRole(['admin_cabang', 'koordinator_cabang', 'super_admin']);
    }

    /** null = semua cabang (super admin). */
    public static function cabangIds(User $user): ?array
    {
        return self::isSuperAdmin($user) ? null : $user->cabang->pluck('id')->all();
    }

    /** null = tidak dibatasi; array = hanya program yang diajar guru ini. */
    public static function guruKelasIds(User $user): ?array
    {
        if (! self::isGuruOnly($user)) {
            return null;
        }

        $guruId = $user->guru?->id;

        return Kelas::whereHas('jadwal', fn ($q) => $q->where('guru_id', $guruId))
            ->pluck('id')
            ->all();
    }

    public static function siswaQuery(User $user, ?int $cabangId = null, bool $withTrashed = false): Builder
    {
        $cabangIds = self::cabangIds($user);
        $kelasIdsGuru = self::guruKelasIds($user);

        return Siswa::query()
            ->when($withTrashed, fn ($q) => $q->withTrashed())
            ->when($cabangIds !== null, fn ($q) => $q->whereIn('cabang_id', $cabangIds))
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when($kelasIdsGuru !== null, fn ($q) => $q->whereHas(
                'kelasList',
                fn ($q2) => $q2->whereIn('kelas.id', $kelasIdsGuru)
            ));
    }
}
