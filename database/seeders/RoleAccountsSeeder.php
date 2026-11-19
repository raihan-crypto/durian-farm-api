<?php

namespace Database\Seeders;

use App\Models\AgriculturalProduct;
use App\Models\Block;
use App\Models\BlockFarmer;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use App\Services\TreeQrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class RoleAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $qrService = app(TreeQrCodeService::class);

        // 1. Organisasi Induk
        $org = Organization::firstOrCreate(
            ['name' => 'PT Perkebunan Durian Nusantara'],
            ['description' => 'Kantor Pusat Pengelola Perkebunan Durian Modern']
        );

        // 2. Data Wilayah Dasar (Desa, Blok, Kebun)
        $village = Village::firstOrCreate(
            ['name' => 'Desa Cikidang'],
            [
                'district' => 'Cikidang',
                'regency' => 'Sukabumi',
                'province' => 'Jawa Barat',
            ]
        );

        $block = Block::firstOrCreate(
            ['name' => 'Blok A - Harapan', 'village_id' => $village->id],
            [
                'area_hectares' => 12.5,
                'description' => 'Blok varietas unggulan ekspor',
            ]
        );

        $garden = Garden::firstOrCreate(
            ['name' => 'Kebun Durian Bukit Makmur', 'block_id' => $block->id],
            [
                'area_hectares' => 5.5,
                'address' => 'Lereng Bukit Makmur Kav. 1-10, Cikidang',
                'latitude' => -6.912345,
                'longitude' => 106.823456,
                'description' => 'Kebun percontohan durian premium dengan sistem irigasi tetes',
            ]
        );

        // 3. Master Varietas Durian
        $varMK = DurianVariety::updateOrCreate(
            ['name' => 'Musang King'],
            [
                'code' => 'MK',
                'origin' => 'Malaysia',
                'description' => 'Durian primadona dengan rasa creamy manis dan sentuhan pahit lembut.',
                'characteristics' => 'Daging tebal berwarna kuning kunyit cerah, biji kempis, aroma semerbak.',
                'is_active' => true,
            ]
        );

        $varDH = DurianVariety::updateOrCreate(
            ['name' => 'Duri Hitam (Ochee)'],
            [
                'code' => 'DH',
                'origin' => 'Malaysia',
                'description' => 'Durian eksklusif dengan tekstur lembut halus bagaikan mentega.',
                'characteristics' => 'Daging tebal oranye kemerahan, aroma harum kuat, manis legit gurih.',
                'is_active' => true,
            ]
        );

        $varBW = DurianVariety::updateOrCreate(
            ['name' => 'Bawor'],
            [
                'code' => 'BW',
                'origin' => 'Banyumas, Indonesia',
                'description' => 'Durian lokal unggul adaptif berkaki ganda dengan produktivitas tinggi.',
                'characteristics' => 'Daging oranye tebal, rasa legit manis pahit, bobot buah besar.',
                'is_active' => true,
            ]
        );

        // 4. Sample Pohon Durian & Generate QR Code
        $tree1 = Tree::firstOrCreate(
            ['tree_code' => 'MK-001', 'garden_id' => $garden->id],
            [
                'variety_id' => $varMK->id,
                'tree_name' => 'Pohon Musang King Inti 1',
                'planted_at' => Carbon::now()->subYears(4)->toDateString(),
                'planting_age_months' => 48,
                'initial_diameter_cm' => 22.5,
                'initial_height_m' => 5.2,
                'initial_canopy_width_m' => 4.0,
                'latitude' => -6.912350,
                'longitude' => 106.823460,
                'status' => 'active',
                'notes' => 'Pohon induk produktivitas tinggi',
            ]
        );
        $qrService->ensureForTree($tree1);

        $tree2 = Tree::firstOrCreate(
            ['tree_code' => 'DH-001', 'garden_id' => $garden->id],
            [
                'variety_id' => $varDH->id,
                'tree_name' => 'Pohon Duri Hitam Unggul 1',
                'planted_at' => Carbon::now()->subYears(3)->toDateString(),
                'planting_age_months' => 36,
                'initial_diameter_cm' => 18.0,
                'initial_height_m' => 4.5,
                'initial_canopy_width_m' => 3.5,
                'latitude' => -6.912400,
                'longitude' => 106.823500,
                'status' => 'active',
                'notes' => 'Fase pembungaan awal',
            ]
        );
        $qrService->ensureForTree($tree2);

        $tree3 = Tree::firstOrCreate(
            ['tree_code' => 'BW-001', 'garden_id' => $garden->id],
            [
                'variety_id' => $varBW->id,
                'tree_name' => 'Pohon Bawor Blok A',
                'planted_at' => Carbon::now()->subYears(2)->toDateString(),
                'planting_age_months' => 24,
                'initial_diameter_cm' => 14.5,
                'initial_height_m' => 3.8,
                'initial_canopy_width_m' => 2.9,
                'latitude' => -6.912450,
                'longitude' => 106.823550,
                'status' => 'monitoring',
                'notes' => 'Pemulihan pasca pemangkasan tunas air',
            ]
        );
        $qrService->ensureForTree($tree3);

        // 5. Master Produk Pertanian
        $catPupuk = ProductCategory::firstOrCreate(
            ['name' => 'Pupuk Tanaman'],
            ['description' => 'Pupuk organik dan anorganik untuk nutrisi tanaman durian', 'is_active' => true]
        );
        $catPestisida = ProductCategory::firstOrCreate(
            ['name' => 'Pestisida & Fungisida'],
            ['description' => 'Proteksi tanaman dari hama serangga dan jamur', 'is_active' => true]
        );

        AgriculturalProduct::firstOrCreate(
            ['name' => 'NPK Mutiara 16-16-16', 'category_id' => $catPupuk->id],
            ['unit' => 'kg', 'description' => 'Pupuk majemuk seimbang untuk fase vegetatif dan generatif', 'is_active' => true]
        );
        AgriculturalProduct::firstOrCreate(
            ['name' => 'Pupuk Kandang Kambing Fermentasi', 'category_id' => $catPupuk->id],
            ['unit' => 'kg', 'description' => 'Bahan organik pembenah struktur tanah dan mikroorganisme', 'is_active' => true]
        );
        AgriculturalProduct::firstOrCreate(
            ['name' => 'Antracol 70 WP (Fungisida)', 'category_id' => $catPestisida->id],
            ['unit' => 'gram', 'description' => 'Fungisida kontak berspektrum luas pengendali busuk akar dan jamur daun', 'is_active' => true]
        );

        // 6. Akun Pengguna Tiap-tiap Role (SRS Bab 8)
        $roles = [
            'super_admin' => Role::where('name', 'super_admin')->first(),
            'admin' => Role::where('name', 'admin')->first(),
            'plantation_manager' => Role::where('name', 'plantation_manager')->first(),
            'farmer' => Role::where('name', 'farmer')->first(),
            'owner' => Role::where('name', 'owner')->first(),
        ];

        $defaultPassword = Hash::make('Password123!');

        // 6.1 Super Administrator
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Super Administrator',
                'password' => $defaultPassword,
                'phone' => '081234567801',
                'is_active' => true,
            ]
        );
        if ($roles['super_admin'] && ! $superAdmin->hasRole('super_admin')) {
            $superAdmin->roles()->syncWithoutDetaching([$roles['super_admin']->id]);
        }

        // Juga pastikan admin@durianfarm.test tetap ada untuk backward compatibility
        $adminOld = User::firstOrCreate(
            ['email' => 'admin@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Administrator Perkebunan',
                'password' => $defaultPassword,
                'phone' => '081234567802',
                'is_active' => true,
            ]
        );
        if ($roles['admin'] && ! $adminOld->hasRole('admin')) {
            $adminOld->roles()->syncWithoutDetaching([$roles['admin']->id]);
        }

        // 6.2 Administrator
        $adminUser = User::firstOrCreate(
            ['email' => 'admin_kebun@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Admin Operasional Perkebunan',
                'password' => $defaultPassword,
                'phone' => '081234567802',
                'is_active' => true,
            ]
        );
        if ($roles['admin'] && ! $adminUser->hasRole('admin')) {
            $adminUser->roles()->syncWithoutDetaching([$roles['admin']->id]);
        }

        // 6.3 Pengelola Perkebunan (Plantation Manager)
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Ir. Bambang Santoso (Pengelola)',
                'password' => $defaultPassword,
                'phone' => '081234567803',
                'is_active' => true,
            ]
        );
        if ($roles['plantation_manager'] && ! $managerUser->hasRole('plantation_manager')) {
            $managerUser->roles()->syncWithoutDetaching([$roles['plantation_manager']->id]);
        }

        // 6.4 Petani / Pekerja Lapangan (Farmer)
        $farmerUser = User::firstOrCreate(
            ['email' => 'petani@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Suryono (Petani Lapangan)',
                'password' => $defaultPassword,
                'phone' => '081234567804',
                'is_active' => true,
            ]
        );
        if ($roles['farmer'] && ! $farmerUser->hasRole('farmer')) {
            $farmerUser->roles()->syncWithoutDetaching([$roles['farmer']->id]);
        }

        // Hubungkan akun user petani dengan tabel 'farmers' dan beri penugasan wilayah
        $farmerProfile = Farmer::firstOrCreate(
            ['user_id' => $farmerUser->id],
            [
                'name' => 'Suryono',
                'phone' => '081234567804',
                'address' => 'Kp. Durian Harapan RT 02 / RW 04, Cikidang',
            ]
        );

        // Penugasan Petani ke Blok dan Kebun
        BlockFarmer::firstOrCreate(
            ['block_id' => $block->id, 'farmer_id' => $farmerProfile->id],
            ['assignment_date' => Carbon::now()->subMonths(6)->toDateString()]
        );
        GardenFarmer::firstOrCreate(
            ['garden_id' => $garden->id, 'farmer_id' => $farmerProfile->id],
            ['assignment_date' => Carbon::now()->subMonths(6)->toDateString()]
        );

        // 6.5 Pemilik Kebun (Owner)
        $ownerUser = User::firstOrCreate(
            ['email' => 'pemilik@durianfarm.test'],
            [
                'organization_id' => $org->id,
                'name' => 'H. Rahmat Hidayat (Pemilik Kebun)',
                'password' => $defaultPassword,
                'phone' => '081234567805',
                'is_active' => true,
            ]
        );
        if ($roles['owner'] && ! $ownerUser->hasRole('owner')) {
            $ownerUser->roles()->syncWithoutDetaching([$roles['owner']->id]);
        }

        $ownerProfile = Owner::firstOrCreate(
            ['name' => 'H. Rahmat Hidayat'],
            [
                'owner_type' => 'individual',
                'phone' => '081234567805',
                'address' => 'Jl. Kebun Raya No. 45, Sukabumi',
            ]
        );

        // Hubungkan kepemilikan kebun
        GardenOwner::firstOrCreate(
            ['garden_id' => $garden->id, 'owner_id' => $ownerProfile->id],
            [
                'ownership_percentage' => 100,
                'start_date' => Carbon::now()->subYears(5)->toDateString(),
            ]
        );
    }
}
