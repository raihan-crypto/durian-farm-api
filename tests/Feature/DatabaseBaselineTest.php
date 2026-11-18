<?php

namespace Tests\Feature;

use App\Models\AgriculturalProduct;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\FertilizationItem;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Models\HarvestDetail;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\IotDevice;
use App\Models\IotTelemetry;
use App\Models\MonitoringArea;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Permission;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\SprayingItem;
use App\Models\SprayingRecord;
use App\Models\SyncLog;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\TreeQrCode;
use App\Models\User;
use App\Models\Village;
use App\Models\WeatherRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_37_tables_exist_in_database(): void
    {
        $tables = [
            'organizations',
            'roles',
            'permissions',
            'users',
            'user_roles',
            'role_permissions',
            'owners',
            'farmers',
            'villages',
            'blocks',
            'gardens',
            'garden_owners',
            'garden_farmers',
            'block_farmers',
            'durian_varieties',
            'trees',
            'tree_measurements',
            'product_categories',
            'agricultural_products',
            'fertilization_records',
            'fertilization_items',
            'spraying_records',
            'spraying_items',
            'flowering_records',
            'fruit_development_records',
            'harvest_seasons',
            'harvest_sessions',
            'harvest_records',
            'harvest_details',
            'monitoring_areas',
            'weather_records',
            'iot_devices',
            'iot_telemetry',
            'tree_qr_codes',
            'activity_photos',
            'audit_logs',
            'sync_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} should exist in database");
        }
    }

    public function test_eloquent_crud_and_relationships_in_transaction(): void
    {
        DB::beginTransaction();

        try {
            // 1. Organization & User
            $org = Organization::create([
                'name' => 'PT Perkebunan Durian Nusantara',
                'description' => 'Organisasi pengelola perkebunan durian',
            ]);
            $this->assertNotEmpty($org->id);

            $user = User::create([
                'organization_id' => $org->id,
                'name' => 'Budi Santoso',
                'email' => 'budi@durianfarm.test',
                'password' => 'secret123',
                'phone' => '081234567890',
                'is_active' => true,
            ]);
            $this->assertNotEmpty($user->id);
            $this->assertSame($org->id, $user->organization->id);

            // 2. Role & Permission
            $role = Role::create([
                'name' => 'super_admin',
                'description' => 'Super Administrator',
            ]);
            $perm = Permission::create([
                'name' => 'manage_all',
                'description' => 'Kelola seluruh data sistem',
            ]);
            $role->permissions()->attach($perm->id);
            $user->roles()->attach($role->id);

            $this->assertTrue($user->hasRole('super_admin'));
            $this->assertTrue($user->hasPermission('manage_all'));

            // 3. Village, Block, Garden
            $village = Village::create([
                'name' => 'Desa Rancamaya',
                'district' => 'Bogor Selatan',
                'regency' => 'Bogor',
                'province' => 'Jawa Barat',
            ]);

            $block = Block::create([
                'village_id' => $village->id,
                'name' => 'Blok A',
                'area_hectares' => 5.2500,
            ]);
            $this->assertSame($village->id, $block->village->id);

            $garden = Garden::create([
                'block_id' => $block->id,
                'name' => 'Kebun Durian Bukit Asri',
                'area_hectares' => 2.5000,
                'latitude' => -6.6543210,
                'longitude' => 106.8123450,
            ]);
            $this->assertSame($block->id, $garden->block->id);

            // 4. Owner & Farmer
            $owner = Owner::create([
                'owner_type' => 'individual',
                'name' => 'Haji Mansur',
                'phone' => '08111222333',
            ]);
            GardenOwner::create([
                'garden_id' => $garden->id,
                'owner_id' => $owner->id,
                'ownership_percentage' => 100.00,
                'start_date' => '2024-01-01',
            ]);
            $this->assertCount(1, $garden->owners);

            $farmer = Farmer::create([
                'user_id' => $user->id,
                'name' => 'Pak Joko',
                'phone' => '081987654321',
            ]);
            GardenFarmer::create([
                'garden_id' => $garden->id,
                'farmer_id' => $farmer->id,
                'assignment_date' => '2024-01-01',
            ]);
            $this->assertCount(1, $garden->farmers);

            // 5. Variety, Tree, TreeMeasurement, TreeQrCode
            $variety = DurianVariety::create([
                'name' => 'Musang King',
                'origin' => 'Malaysia',
                'description' => 'Daging kuning keemasan, rasa manis legit agak pahit',
            ]);

            $tree = Tree::create([
                'garden_id' => $garden->id,
                'variety_id' => $variety->id,
                'tree_code' => 'MK-001',
                'tree_name' => 'Pohon Musang King 01',
                'planted_at' => '2020-03-15',
                'planting_age_months' => 48,
                'initial_diameter_cm' => 15.50,
                'initial_height_m' => 3.20,
                'initial_canopy_width_m' => 2.80,
                'status' => 'active',
            ]);

            $qr = TreeQrCode::create([
                'tree_id' => $tree->id,
                'qr_code' => 'QR-DUR-MK001-TEST',
                'public_token' => 'pub-tok-test-001',
                'is_active' => true,
            ]);
            $this->assertSame($qr->id, $tree->qrCode->id);

            $measurement = TreeMeasurement::create([
                'tree_id' => $tree->id,
                'measured_at' => now(),
                'diameter_cm' => 22.40,
                'height_m' => 4.50,
                'canopy_width_m' => 3.60,
                'recorded_by' => $user->id,
            ]);
            $this->assertSame($tree->id, $measurement->tree->id);

            // 6. Product, Fertilization, Spraying
            $category = ProductCategory::create([
                'name' => 'Pupuk Organik',
                'description' => 'Pupuk alami berbahan kompos dan kotoran hewan',
            ]);

            $product = AgriculturalProduct::create([
                'category_id' => $category->id,
                'name' => 'Kompos Fermentasi Super',
                'brand' => 'AgroLestari',
                'unit' => 'kg',
            ]);

            $fertRecord = FertilizationRecord::create([
                'tree_id' => $tree->id,
                'activity_date' => now(),
                'method' => 'tabur',
                'dosage_notes' => '10 kg per pohon',
                'recorded_by' => $user->id,
            ]);

            FertilizationItem::create([
                'fertilization_record_id' => $fertRecord->id,
                'product_id' => $product->id,
                'quantity' => 10.000,
                'unit' => 'kg',
            ]);
            $this->assertCount(1, $fertRecord->items);

            $sprayRecord = SprayingRecord::create([
                'tree_id' => $tree->id,
                'activity_date' => now(),
                'spraying_method' => 'Mist Blower',
                'recorded_by' => $user->id,
            ]);

            SprayingItem::create([
                'spraying_record_id' => $sprayRecord->id,
                'product_id' => $product->id,
                'quantity' => 0.500,
                'unit' => 'liter',
            ]);
            $this->assertCount(1, $sprayRecord->items);

            // 7. Flowering & Fruit Development
            $flowering = FloweringRecord::create([
                'tree_id' => $tree->id,
                'flowering_date' => '2024-05-10',
                'flowering_status' => 'berbunga',
                'flower_count' => 150,
                'recorded_by' => $user->id,
            ]);
            $this->assertSame($tree->id, $flowering->tree->id);

            $fruitDev = FruitDevelopmentRecord::create([
                'tree_id' => $tree->id,
                'observation_date' => '2024-06-15',
                'fruit_count' => 45,
                'development_stage' => 'pembesaran',
                'recorded_by' => $user->id,
            ]);
            $this->assertSame($tree->id, $fruitDev->tree->id);

            // 8. Harvest Season, Session, Record, Detail
            $season = HarvestSeason::create([
                'garden_id' => $garden->id,
                'name' => 'Musim Panen Raya 2024',
                'start_date' => '2024-08-01',
                'end_date' => '2024-09-30',
                'status' => 'active',
            ]);

            $session = HarvestSession::create([
                'season_id' => $season->id,
                'garden_id' => $garden->id,
                'harvest_date' => now(),
                'session_name' => 'Sesi Panen Pagi 01',
                'recorded_by' => $user->id,
            ]);

            $harvestRecord = HarvestRecord::create([
                'session_id' => $session->id,
                'garden_id' => $garden->id,
                'tree_id' => $tree->id,
                'fruit_count' => 12,
                'total_weight_kg' => 24.500,
            ]);

            $harvestDetail = HarvestDetail::create([
                'harvest_record_id' => $harvestRecord->id,
                'fruit_grade' => 'Grade A',
                'fruit_count' => 8,
                'weight_kg' => 17.200,
            ]);
            $this->assertSame($harvestRecord->id, $harvestDetail->harvestRecord->id);

            // 9. Monitoring Area, Weather, IoT
            $area = MonitoringArea::create([
                'garden_id' => $garden->id,
                'name' => 'Zona Barat Laut',
                'latitude' => -6.6543000,
                'longitude' => 106.8123000,
            ]);

            $weather = WeatherRecord::create([
                'monitoring_area_id' => $area->id,
                'garden_id' => $garden->id,
                'recorded_at' => now(),
                'temperature_c' => 28.50,
                'humidity_percent' => 75.00,
                'rainfall_mm' => 0.00,
                'weather_condition' => 'Cerah Berawan',
                'source' => 'OpenWeatherMap',
                'raw_data' => ['temp' => 28.5, 'humidity' => 75],
            ]);
            $this->assertSame($garden->id, $weather->garden->id);

            $device = IotDevice::create([
                'monitoring_area_id' => $area->id,
                'device_code' => 'IOT-SENS-001',
                'device_name' => 'Soil & Weather Station 1',
                'device_type' => 'microclimate',
                'status' => 'active',
            ]);

            $telemetry = IotTelemetry::create([
                'device_id' => $device->id,
                'recorded_at' => now(),
                'temperature_c' => 28.50,
                'soil_moisture_percent' => 65.20,
            ]);
            $this->assertGreaterThan(0, $telemetry->id);

            // 10. Audit & Sync logs
            $audit = AuditLog::create([
                'user_id' => $user->id,
                'action' => 'create',
                'table_name' => 'trees',
                'record_id' => $tree->id,
                'new_values' => ['tree_code' => 'MK-001'],
            ]);
            $this->assertGreaterThan(0, $audit->id);

            $sync = SyncLog::create([
                'user_id' => $user->id,
                'device_identifier' => 'pixel-7-joko',
                'client_transaction_id' => 'tx-client-uuid-001',
                'entity_type' => 'fertilization_record',
                'entity_id' => $fertRecord->id,
                'sync_status' => 'success',
            ]);
            $this->assertGreaterThan(0, $sync->id);

        } finally {
            DB::rollBack();
        }
    }
}
