<?php

namespace App\Policies;

use App\Models\ReportSiswa;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportSiswaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_report::siswa');
    }

    public function view(User $user, ReportSiswa $reportSiswa): bool
    {
        return $user->can('view_report::siswa');
    }

    public function create(User $user): bool
    {
        return $user->can('create_report::siswa');
    }

    /** Snapshot tidak bisa diedit; koreksi lewat "Buat Ulang". */
    public function update(User $user, ReportSiswa $reportSiswa): bool
    {
        return false;
    }

    public function delete(User $user, ReportSiswa $reportSiswa): bool
    {
        return $user->can('delete_report::siswa');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_report::siswa');
    }

    public function forceDelete(User $user, ReportSiswa $reportSiswa): bool
    {
        return $user->can('force_delete_report::siswa');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_report::siswa');
    }

    public function restore(User $user, ReportSiswa $reportSiswa): bool
    {
        return $user->can('restore_report::siswa');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_report::siswa');
    }

    public function replicate(User $user, ReportSiswa $reportSiswa): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
