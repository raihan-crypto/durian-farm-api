<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Models\MonitoringArea;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiControllerAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $adminToken;

    protected User $scopedUser;

    protected string $scopedUserToken;

    protected Village $villageA;

    protected Village $villageB;

    protected Block $blockA;

    protected Block $blockB;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected Farmer $farmer;

    protected Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $org = Organization::create(['name' => 'Organisasi Perkebunan']);

        $this->admin = User::create([
            'organization_id' => $org->id,
            'name' => 'Admin User',
            'email' => 'admin.scope@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

        // Scoped user: has territory.manage/gardens.manage permissions, but assigned only to Garden A
        $this->scopedUser = User::create([
            'organization_id' => $org->id,
            'name' => 'Scoped Supervisor',
            'email' => 'supervisor.scope@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $supervisorRole = Role::firstOrCreate(
            ['name' => 'supervisor'],
            ['description' => 'Supervisor Wilayah']
        );
        $permissions = Permission::whereIn('name', [
            'territory.manage',
            'territory.view',
            'gardens.manage',
            'ownership.manage',
            'ownership.view',
        ])->get();
        $supervisorRole->permissions()->sync($permissions);
        $this->scopedUser->roles()->attach($supervisorRole);
        $this->scopedUserToken = $this->scopedUser->createToken('scoped')->plainTextToken;

        $this->farmer = Farmer::create([
            'user_id' => $this->scopedUser->id,
            'name' => 'Scoped Farmer Account',
            'farmer_type' => 'permanent',
        ]);

        $this->owner = Owner::create([
            'user_id' => $this->scopedUser->id,
            'name' => 'Scoped Owner Account',
            'owner_type' => 'individual',
        ]);

        // Village A & Block A & Garden A (assigned to scoped user)
        $this->villageA = Village::create([
            'name' => 'Desa Terjangkau A',
            'district' => 'Kecamatan A',
            'regency' => 'Kabupaten A',
            'province' => 'Provinsi A',
        ]);

        $this->blockA = Block::create([
            'village_id' => $this->villageA->id,
            'name' => 'Blok A',
            'approval_status' => 'approved',
        ]);

        $this->gardenA = Garden::create([
            'block_id' => $this->blockA->id,
            'name' => 'Kebun A Terjangkau',
            'status' => 'active',
        ]);

        // Village B & Block B & Garden B (NOT assigned to scoped user)
        $this->villageB = Village::create([
            'name' => 'Desa Asing B',
            'district' => 'Kecamatan B',
            'regency' => 'Kabupaten B',
            'province' => 'Provinsi B',
        ]);

        $this->blockB = Block::create([
            'village_id' => $this->villageB->id,
            'name' => 'Blok B',
            'approval_status' => 'approved',
        ]);

        $this->gardenB = Garden::create([
            'block_id' => $this->blockB->id,
            'name' => 'Kebun B Asing',
            'status' => 'active',
        ]);

        // Assign scoped user only to Garden A
        GardenFarmer::create([
            'garden_id' => $this->gardenA->id,
            'farmer_id' => $this->farmer->id,
            'assignment_date' => now()->toDateString(),
        ]);
    }

    public function test_user_cannot_update_or_delete_unauthorized_village(): void
    {
        // Scoped user does not have access to Village B
        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/villages/{$this->villageB->id}", [
                'name' => 'Desa B Hacked',
                'district' => 'Kecamatan B',
                'regency' => 'Kabupaten B',
                'province' => 'Provinsi B',
            ]);

        $updateResponse->assertStatus(403);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->deleteJson("/api/v1/villages/{$this->villageB->id}");

        $deleteResponse->assertStatus(403);
    }

    public function test_user_cannot_create_or_update_block_in_unauthorized_village(): void
    {
        // Try creating a block in Village B
        $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson('/api/v1/blocks', [
                'village_id' => $this->villageB->id,
                'name' => 'Blok Baru di Desa B',
            ]);

        $createResponse->assertStatus(403);

        // Try updating Block B
        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/blocks/{$this->blockB->id}", [
                'village_id' => $this->villageB->id,
                'name' => 'Blok B Modified',
            ]);

        $updateResponse->assertStatus(403);

        // Try moving Block A to Village B
        $moveResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/blocks/{$this->blockA->id}", [
                'village_id' => $this->villageB->id,
                'name' => 'Blok A Moved to Village B',
            ]);

        $moveResponse->assertStatus(403);
    }

    public function test_user_cannot_delete_unauthorized_block_or_assign_farmer(): void
    {
        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->deleteJson("/api/v1/blocks/{$this->blockB->id}");

        $deleteResponse->assertStatus(403);

        $assignResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson("/api/v1/blocks/{$this->blockB->id}/farmers", [
                'farmer_id' => $this->farmer->id,
                'assignment_date' => now()->toDateString(),
            ]);

        $assignResponse->assertStatus(403);
    }

    public function test_user_cannot_create_garden_in_unauthorized_block(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson('/api/v1/gardens', [
                'block_id' => $this->blockB->id,
                'name' => 'Kebun Baru di Blok B',
                'area_hectares' => 2.5,
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_or_delete_unauthorized_garden(): void
    {
        // Update Garden B
        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/gardens/{$this->gardenB->id}", [
                'block_id' => $this->blockB->id,
                'name' => 'Kebun B Hacked',
            ]);

        $updateResponse->assertStatus(403);

        // Delete Garden B
        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->deleteJson("/api/v1/gardens/{$this->gardenB->id}");

        $deleteResponse->assertStatus(403);
    }

    public function test_user_cannot_manage_owners_or_farmers_on_unauthorized_garden(): void
    {
        // Assign owner on Garden B
        $assignOwnerResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson("/api/v1/gardens/{$this->gardenB->id}/owners", [
                'owner_id' => $this->owner->id,
                'ownership_percentage' => 50,
                'start_date' => now()->toDateString(),
            ]);

        $assignOwnerResponse->assertStatus(403);

        // Assign farmer on Garden B
        $assignFarmerResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson("/api/v1/gardens/{$this->gardenB->id}/farmers", [
                'farmer_id' => $this->farmer->id,
                'assignment_date' => now()->toDateString(),
            ]);

        $assignFarmerResponse->assertStatus(403);
    }

    public function test_monitoring_area_and_iot_device_authorization(): void
    {
        // Create monitoring area in unauthorized Garden B
        $createAreaResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson('/api/v1/monitoring-areas', [
                'garden_id' => $this->gardenB->id,
                'name' => 'Area Pantau B',
            ]);

        $createAreaResponse->assertStatus(403);

        // Create monitoring area in authorized Garden A as admin first
        $areaA = MonitoringArea::create([
            'garden_id' => $this->gardenA->id,
            'name' => 'Area Pantau A',
        ]);

        $areaB = MonitoringArea::create([
            'garden_id' => $this->gardenB->id,
            'name' => 'Area Pantau B Admin',
        ]);

        // Scoped user updates Area B
        $updateAreaResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/monitoring-areas/{$areaB->id}", [
                'garden_id' => $this->gardenB->id,
                'name' => 'Area Pantau B Hacked',
            ]);

        $updateAreaResponse->assertStatus(403);

        // Scoped user deletes Area B
        $deleteAreaResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->deleteJson("/api/v1/monitoring-areas/{$areaB->id}");

        $deleteAreaResponse->assertStatus(403);

        // Register IoT device in unauthorized Area B
        $createDeviceResponse = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson('/api/v1/iot-devices', [
                'monitoring_area_id' => $areaB->id,
                'device_code' => 'IOT-UNAUTH-001',
                'device_name' => 'Sensor Tanah B',
                'device_type' => 'soil_sensor',
            ]);

        $createDeviceResponse->assertStatus(403);

        // Register IoT device in authorized Area A
        $createDeviceSuccess = $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->postJson('/api/v1/iot-devices', [
                'monitoring_area_id' => $areaA->id,
                'device_code' => 'IOT-AUTH-001',
                'device_name' => 'Sensor Tanah A',
                'device_type' => 'soil_sensor',
            ]);

        $createDeviceSuccess->assertStatus(201);
    }

    public function test_user_cannot_update_or_remove_ownership_and_farmer_on_unauthorized_garden(): void
    {
        // Setup existing ownership and farmer assignment on Garden B
        $otherFarmer = Farmer::create([
            'name' => 'Other Farmer',
            'farmer_type' => 'permanent',
        ]);
        $otherOwner = Owner::create([
            'name' => 'Other Owner',
            'owner_type' => 'individual',
        ]);

        $ownershipB = GardenOwner::create([
            'garden_id' => $this->gardenB->id,
            'owner_id' => $otherOwner->id,
            'ownership_percentage' => 30,
            'start_date' => now()->toDateString(),
        ]);

        $farmerAssignmentB = GardenFarmer::create([
            'garden_id' => $this->gardenB->id,
            'farmer_id' => $otherFarmer->id,
            'assignment_date' => now()->toDateString(),
        ]);

        // Scoped user tries to update ownership on Garden B
        $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/gardens/{$this->gardenB->id}/owners/{$ownershipB->id}", [
                'owner_id' => $otherOwner->id,
                'ownership_percentage' => 40,
            ])
            ->assertStatus(403);

        // Scoped user tries to delete ownership on Garden B
        $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->deleteJson("/api/v1/gardens/{$this->gardenB->id}/owners/{$ownershipB->id}")
            ->assertStatus(403);

        // Scoped user tries to update farmer assignment on Garden B
        $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->putJson("/api/v1/gardens/{$this->gardenB->id}/farmers/{$farmerAssignmentB->id}", [
                'farmer_id' => $otherFarmer->id,
                'end_date' => now()->addDays(30)->toDateString(),
            ])
            ->assertStatus(403);

        // Scoped user tries to delete farmer assignment on Garden B
        $this->withHeader('Authorization', 'Bearer '.$this->scopedUserToken)
            ->deleteJson("/api/v1/gardens/{$this->gardenB->id}/farmers/{$farmerAssignmentB->id}")
            ->assertStatus(403);
    }

    public function test_admin_has_unrestricted_access_to_all_territories_and_iot(): void
    {
        // Admin can update Village B
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/villages/{$this->villageB->id}", [
                'name' => 'Desa B Admin Updated',
                'district' => 'Kecamatan B',
                'regency' => 'Kabupaten B',
                'province' => 'Provinsi B',
            ])
            ->assertStatus(200);

        // Admin can update Block B
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/blocks/{$this->blockB->id}", [
                'village_id' => $this->villageB->id,
                'name' => 'Blok B Admin Updated',
            ])
            ->assertStatus(200);

        // Admin can update Garden B
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/gardens/{$this->gardenB->id}", [
                'block_id' => $this->blockB->id,
                'name' => 'Kebun B Admin Updated',
            ])
            ->assertStatus(200);
    }
}
