<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['name' => 'PT Perkebunan Durian Nusantara'],
            ['description' => 'Kantor Pusat Pengelola Perkebunan Durian']
        );

        $superAdminRole = Role::where('name', 'super_admin')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Super Administrator',
                'password' => Hash::make('Password123!'),
                'phone' => '081234567890',
                'is_active' => true,
            ]
        );

        if ($superAdminRole && ! $admin->hasRole('super_admin')) {
            $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
