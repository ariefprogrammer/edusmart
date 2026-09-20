<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        collect(['finance_pusat', 'koordinator_cabang', 'admin_cabang', 'guru'])
            ->each(fn (string $role) => Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]));
    }
}