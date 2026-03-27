<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superRole  = Role::firstOrCreate(['name' => 'superadmin']);
        $adminRole  = Role::firstOrCreate(['name' => 'admin']);
        $alumnoRole = Role::firstOrCreate(['name' => 'alumno']);

        // ✅ Super Admin
        $super = User::firstOrCreate(
            ['email' => 'super@demo.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('Super12345*'),
                'is_active' => 1,
                'quarter' => null,
            ]
        );

        if (!$super->hasRole('superadmin')) {
            $super->syncRoles([$superRole]);
        }

        // ✅ Admin demo
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin12345*'),
                'is_active' => 1,
                'quarter' => null,
            ]
        );

        if (!$admin->hasRole('admin')) {
            $admin->syncRoles([$adminRole]);
        }
    }
}