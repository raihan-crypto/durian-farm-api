<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleRedesignSeeder extends Seeder
{
    /**
     * Migrate existing roles to the new structure:
     * - owner -> petani
     * - farmer -> tenaga_kerja
     * - plantation_manager -> migrated to petani and deleted
     * - pemerintah_desa -> created
     * - super_admin & admin -> retained
     */
    public function run(): void
    {
        // 1. Ensure new/updated permissions exist
        $newPermissions = [
            ['name' => 'blocks.request', 'description' => 'Mengajukan penambahan atau penghapusan blok oleh pemerintah desa'],
            ['name' => 'gardens.manage', 'description' => 'Mengelola data kebun dan penugasan tenaga kerja kebun'],
            ['name' => 'varieties.manage', 'description' => 'Mengelola master varietas durian'],
        ];

        foreach ($newPermissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], ['description' => $p['description']]);
        }

        $allPermissions = Permission::all()->keyBy('name');

        // 2. Ensure Super Admin role
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'Super Administrator dengan hak akses penuh ke seluruh sistem']
        );
        $superAdmin->permissions()->sync($allPermissions->pluck('id'));

        // 3. Ensure Admin role
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator perkebunan untuk mengelola master data dan aktivitas']
        );
        $adminPermIds = $allPermissions->reject(fn ($p) => in_array($p->name, ['roles.manage', 'organizations.manage', 'audit.view'], true))->pluck('id');
        $admin->permissions()->sync($adminPermIds);

        // 4. Petani (Pengelola Kebun - replaces Owner & absorbs Plantation Manager)
        $petani = Role::firstOrCreate(
            ['name' => 'petani'],
            ['description' => 'Petani (Pengelola Kebun) yang memiliki atau mengelola kebun, menambahkan kebun dan tenaga kerja']
        );
        $petaniPermNames = [
            'territory.view',
            'gardens.manage',
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
        ];
        $petani->permissions()->sync(
            $allPermissions->filter(fn ($p) => in_array($p->name, $petaniPermNames, true))->pluck('id')
        );

        // Migrate users from 'owner' to 'petani'
        $oldOwner = Role::where('name', 'owner')->first();
        if ($oldOwner) {
            $ownerUsers = DB::table('user_roles')->where('role_id', $oldOwner->id)->pluck('user_id');
            foreach ($ownerUsers as $userId) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $petani->id]
                );
            }
        }

        // Migrate users from 'plantation_manager' to 'petani'
        $manager = Role::where('name', 'plantation_manager')->first();
        if ($manager) {
            $managerUsers = DB::table('user_roles')->where('role_id', $manager->id)->pluck('user_id');
            foreach ($managerUsers as $userId) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $petani->id]
                );
            }
            // Remove plantation_manager
            $manager->permissions()->detach();
            DB::table('user_roles')->where('role_id', $manager->id)->delete();
            $manager->delete();
        }

        // 5. Tenaga Kerja Kebun (Field Worker - replaces Farmer)
        $tenagaKerja = Role::firstOrCreate(
            ['name' => 'tenaga_kerja'],
            ['description' => 'Tenaga Kerja Kebun (pekerja lapangan operasional untuk pencatatan lapangan dan sinkronisasi mobile)']
        );
        $tenagaKerjaPermNames = [
            'territory.view',
            'trees.view',
            'trees.measure',
            'activities.record',
            'activities.view',
            'harvest.record',
            'harvest.view',
            'products.view',
            'sync.access',
        ];
        $tenagaKerja->permissions()->sync(
            $allPermissions->filter(fn ($p) => in_array($p->name, $tenagaKerjaPermNames, true))->pluck('id')
        );

        // Migrate users from 'farmer' to 'tenaga_kerja'
        $oldFarmer = Role::where('name', 'farmer')->first();
        if ($oldFarmer) {
            $farmerUsers = DB::table('user_roles')->where('role_id', $oldFarmer->id)->pluck('user_id');
            foreach ($farmerUsers as $userId) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $tenagaKerja->id]
                );
            }
        }

        // 6. Pemerintah Desa
        $pemerintahDesa = Role::firstOrCreate(
            ['name' => 'pemerintah_desa'],
            ['description' => 'Pemerintah Desa / Perangkat Desa untuk memantau ringkasan wilayah desa dan mengajukan blok']
        );
        $desaPermNames = [
            'territory.view',
            'blocks.request',
            'reports.view',
        ];
        $pemerintahDesa->permissions()->sync(
            $allPermissions->filter(fn ($p) => in_array($p->name, $desaPermNames, true))->pluck('id')
        );
    }
}
