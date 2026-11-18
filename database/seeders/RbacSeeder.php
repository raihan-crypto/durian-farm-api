<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User & Organization
            ['name' => 'users.manage', 'description' => 'Mengelola akun pengguna'],
            ['name' => 'users.view', 'description' => 'Melihat daftar pengguna'],
            ['name' => 'roles.manage', 'description' => 'Mengelola role dan permission'],
            ['name' => 'roles.view', 'description' => 'Melihat role dan permission'],
            ['name' => 'organizations.manage', 'description' => 'Mengelola data organisasi'],
            ['name' => 'organizations.view', 'description' => 'Melihat data organisasi'],

            // Territory
            ['name' => 'territory.manage', 'description' => 'Mengelola wilayah, blok, dan kebun'],
            ['name' => 'territory.view', 'description' => 'Melihat data wilayah, blok, dan kebun'],
            ['name' => 'ownership.manage', 'description' => 'Mengelola kepemilikan dan penugasan'],
            ['name' => 'ownership.view', 'description' => 'Melihat kepemilikan dan penugasan'],

            // Trees
            ['name' => 'trees.manage', 'description' => 'Mengelola data pohon dan varietas'],
            ['name' => 'trees.view', 'description' => 'Melihat data pohon dan riwayat'],
            ['name' => 'trees.measure', 'description' => 'Mencatat pengukuran pohon'],

            // Plantation Activities
            ['name' => 'activities.record', 'description' => 'Mencatat aktivitas pemupukan, penyemprotan, pembungaan, buah'],
            ['name' => 'activities.view', 'description' => 'Melihat riwayat aktivitas perkebunan'],

            // Harvest
            ['name' => 'harvest.manage', 'description' => 'Mengelola musim dan sesi panen'],
            ['name' => 'harvest.record', 'description' => 'Mencatat hasil panen per pohon'],
            ['name' => 'harvest.view', 'description' => 'Melihat data dan laporan panen'],

            // Products
            ['name' => 'products.manage', 'description' => 'Mengelola master produk pertanian'],
            ['name' => 'products.view', 'description' => 'Melihat master produk pertanian'],

            // Reports, Audit & Sync
            ['name' => 'reports.view', 'description' => 'Melihat laporan perkebunan'],
            ['name' => 'reports.export', 'description' => 'Mengekspor laporan perkebunan'],
            ['name' => 'audit.view', 'description' => 'Melihat riwayat audit log'],
            ['name' => 'sync.access', 'description' => 'Mengakses API sinkronisasi data mobile'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['name']] = Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['description' => $perm['description']]
            );
        }

        // 1. Super Admin
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'Super Administrator dengan hak akses penuh ke seluruh sistem']
        );
        $superAdmin->permissions()->sync(collect($permissionModels)->pluck('id'));

        // 2. Admin
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator perkebunan untuk mengelola master data dan aktivitas']
        );
        $adminPermissions = collect($permissionModels)->filter(function ($item, $key) {
            return ! in_array($key, ['roles.manage', 'organizations.manage', 'audit.view'], true);
        })->pluck('id');
        $admin->permissions()->sync($adminPermissions);

        // 3. Plantation Manager
        $manager = Role::firstOrCreate(
            ['name' => 'plantation_manager'],
            ['description' => 'Pengelola Perkebunan yang memantau kebun dan aktivitas petani']
        );
        $managerPermissions = collect($permissionModels)->filter(function ($item, $key) {
            return in_array($key, [
                'territory.view',
                'ownership.view',
                'trees.view',
                'trees.manage',
                'trees.measure',
                'activities.view',
                'activities.record',
                'harvest.view',
                'harvest.record',
                'products.view',
                'reports.view',
                'reports.export',
            ], true);
        })->pluck('id');
        $manager->permissions()->sync($managerPermissions);

        // 4. Farmer
        $farmer = Role::firstOrCreate(
            ['name' => 'farmer'],
            ['description' => 'Petani atau Pekerja Lapangan untuk pencatatan lapangan dan sinkronisasi']
        );
        $farmerPermissions = collect($permissionModels)->filter(function ($item, $key) {
            return in_array($key, [
                'territory.view',
                'trees.view',
                'trees.measure',
                'activities.record',
                'activities.view',
                'harvest.record',
                'harvest.view',
                'products.view',
                'sync.access',
            ], true);
        })->pluck('id');
        $farmer->permissions()->sync($farmerPermissions);

        // 5. Owner
        $owner = Role::firstOrCreate(
            ['name' => 'owner'],
            ['description' => 'Pemilik Kebun untuk memantau pohon dan laporan panen miliknya']
        );
        $ownerPermissions = collect($permissionModels)->filter(function ($item, $key) {
            return in_array($key, [
                'territory.view',
                'trees.view',
                'activities.view',
                'harvest.view',
                'reports.view',
            ], true);
        })->pluck('id');
        $owner->permissions()->sync($ownerPermissions);
    }
}
