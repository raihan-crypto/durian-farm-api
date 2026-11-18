<?php

namespace Tests\Unit;

use App\Models\ActivityPhoto;
use App\Models\AgriculturalProduct;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\BlockFarmer;
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
use App\Models\RolePermission;
use App\Models\SprayingItem;
use App\Models\SprayingRecord;
use App\Models\SyncLog;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\TreeQrCode;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Village;
use App\Models\WeatherRecord;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPUnit\Framework\TestCase;

class ModelsFoundationTest extends TestCase
{
    public function test_all_37_models_can_be_instantiated_with_correct_tables(): void
    {
        $expected = [
            Organization::class => 'organizations',
            Role::class => 'roles',
            Permission::class => 'permissions',
            User::class => 'users',
            UserRole::class => 'user_roles',
            RolePermission::class => 'role_permissions',
            Owner::class => 'owners',
            Farmer::class => 'farmers',
            Village::class => 'villages',
            Block::class => 'blocks',
            Garden::class => 'gardens',
            GardenOwner::class => 'garden_owners',
            GardenFarmer::class => 'garden_farmers',
            BlockFarmer::class => 'block_farmers',
            DurianVariety::class => 'durian_varieties',
            Tree::class => 'trees',
            TreeMeasurement::class => 'tree_measurements',
            ProductCategory::class => 'product_categories',
            AgriculturalProduct::class => 'agricultural_products',
            FertilizationRecord::class => 'fertilization_records',
            FertilizationItem::class => 'fertilization_items',
            SprayingRecord::class => 'spraying_records',
            SprayingItem::class => 'spraying_items',
            FloweringRecord::class => 'flowering_records',
            FruitDevelopmentRecord::class => 'fruit_development_records',
            HarvestSeason::class => 'harvest_seasons',
            HarvestSession::class => 'harvest_sessions',
            HarvestRecord::class => 'harvest_records',
            HarvestDetail::class => 'harvest_details',
            MonitoringArea::class => 'monitoring_areas',
            WeatherRecord::class => 'weather_records',
            IotDevice::class => 'iot_devices',
            IotTelemetry::class => 'iot_telemetry',
            TreeQrCode::class => 'tree_qr_codes',
            ActivityPhoto::class => 'activity_photos',
            AuditLog::class => 'audit_logs',
            SyncLog::class => 'sync_logs',
        ];

        $this->assertCount(37, $expected);

        foreach ($expected as $class => $table) {
            $instance = new $class;
            $this->assertSame($table, $instance->getTable());
        }
    }

    public function test_user_extends_authenticatable(): void
    {
        $user = new User;
        $this->assertInstanceOf(Authenticatable::class, $user);
        $this->assertSame('string', $user->getKeyType());
        $this->assertFalse($user->getIncrementing());
    }

    public function test_auto_incrementing_models(): void
    {
        $auditLog = new AuditLog;
        $this->assertTrue($auditLog->getIncrementing());
        $this->assertSame('int', $auditLog->getKeyType());

        $telemetry = new IotTelemetry;
        $this->assertTrue($telemetry->getIncrementing());
        $this->assertSame('int', $telemetry->getKeyType());

        $syncLog = new SyncLog;
        $this->assertTrue($syncLog->getIncrementing());
        $this->assertSame('int', $syncLog->getKeyType());
    }

    public function test_uuid_models_have_string_keys(): void
    {
        $tree = new Tree;
        $this->assertFalse($tree->getIncrementing());
        $this->assertSame('string', $tree->getKeyType());

        $garden = new Garden;
        $this->assertFalse($garden->getIncrementing());
        $this->assertSame('string', $garden->getKeyType());
    }
}
