<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenOwner;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $petani;

    protected string $petaniToken;

    protected string $adminToken;

    protected Village $village;

    protected Block $block;

    protected Garden $garden;

    protected Owner $petaniOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $org = Organization::create(['name' => 'Organisasi Perkebunan']);

        $this->superAdmin = User::create([
            'organization_id' => $org->id,
            'name' => 'Super Admin',
            'email' => 'superadmin.rbac@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        $this->admin = User::create([
            'organization_id' => $org->id,
            'name' => 'Admin Perkebunan',
            'email' => 'admin.rbac@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

        $this->petani = User::create([
            'organization_id' => $org->id,
            'name' => 'Petani Pengelola',
            'email' => 'petani.rbac@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->petani->roles()->attach(Role::where('name', 'petani')->firstOrFail());
        $this->petaniToken = $this->petani->createToken('petani')->plainTextToken;

        $this->petaniOwner = Owner::create([
            'user_id' => $this->petani->id,
            'name' => 'Petani Pengelola',
            'owner_type' => 'individual',
        ]);

        $this->village = Village::create([
            'name' => 'Desa Cikidang Test',
            'district' => 'Cikidang',
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);

        $this->block = Block::create([
            'village_id' => $this->village->id,
            'name' => 'Blok 1 Test',
            'approval_status' => 'approved',
        ]);

        $this->garden = Garden::create([
            'block_id' => $this->block->id,
            'name' => 'Kebun Petani Test',
            'status' => 'active',
        ]);

        GardenOwner::create([
            'garden_id' => $this->garden->id,
            'owner_id' => $this->petaniOwner->id,
            'ownership_percentage' => 100,
            'start_date' => now()->toDateString(),
        ]);
    }

    public function test_petani_cannot_create_update_or_delete_village_via_api(): void
    {
        // 1. Create village
        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/villages', [
                'name' => 'Desa Baru Ilegal',
                'district' => 'Kecamatan X',
                'regency' => 'Kabupaten Y',
                'province' => 'Jawa Barat',
            ]);
        $createResp->assertStatus(403);

        // 2. Update village
        $updateResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->putJson('/api/v1/villages/'.$this->village->id, [
                'name' => 'Desa Diubah Petani',
            ]);
        $updateResp->assertStatus(403);

        // 3. Delete village
        $deleteResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->deleteJson('/api/v1/villages/'.$this->village->id);
        $deleteResp->assertStatus(403);
    }

    public function test_petani_cannot_create_update_or_delete_block_via_api(): void
    {
        // 1. Create block
        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/blocks', [
                'village_id' => $this->village->id,
                'name' => 'Blok Ilegal',
                'area_hectares' => 5.0,
            ]);
        $createResp->assertStatus(403);

        // 2. Update block
        $updateResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->putJson('/api/v1/blocks/'.$this->block->id, [
                'name' => 'Blok Diubah Petani',
            ]);
        $updateResp->assertStatus(403);

        // 3. Delete block
        $deleteResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->deleteJson('/api/v1/blocks/'.$this->block->id);
        $deleteResp->assertStatus(403);
    }

    public function test_petani_cannot_approve_or_reject_block_requests(): void
    {
        $pendingBlock = Block::create([
            'village_id' => $this->village->id,
            'name' => 'Blok Usulan',
            'approval_status' => 'pending_addition',
        ]);

        // Attempt approve
        $approveResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/blocks/'.$pendingBlock->id.'/approve');
        $approveResp->assertStatus(403);

        // Attempt reject
        $rejectResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/blocks/'.$pendingBlock->id.'/reject');
        $rejectResp->assertStatus(403);
    }

    public function test_petani_cannot_create_update_or_delete_durian_varieties(): void
    {
        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/durian-varieties', [
                'name' => 'Varietas Palsu',
                'code' => 'VP',
            ]);
        $createResp->assertStatus(403);

        $variety = DurianVariety::create(['name' => 'Varietas Asli', 'code' => 'VA']);

        $updateResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->putJson('/api/v1/durian-varieties/'.$variety->id, [
                'name' => 'Varietas Dimutasi',
            ]);
        $updateResp->assertStatus(403);

        $deleteResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->deleteJson('/api/v1/durian-varieties/'.$variety->id);
        $deleteResp->assertStatus(403);
    }

    public function test_petani_cannot_manage_owners_or_farmers_master_data(): void
    {
        $ownerResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/owners', [
                'name' => 'Pemilik Ilegal',
                'owner_type' => 'individual',
            ]);
        $ownerResp->assertStatus(403);

        $farmerResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/farmers', [
                'name' => 'Pekerja Ilegal',
                'phone' => '08123456789',
            ]);
        $farmerResp->assertStatus(403);

        // Petani cannot assign farmer to block
        $blockAssignResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/blocks/'.$this->block->id.'/farmers', [
                'farmer_id' => 'dummy-id',
            ]);
        $blockAssignResp->assertStatus(403);
    }

    public function test_petani_cannot_trigger_weather_fetch_or_manage_iot_devices(): void
    {
        $weatherResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/weather/fetch');
        $weatherResp->assertStatus(403);

        $iotResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/iot-devices', [
                'name' => 'Sensor Ilegal',
            ]);
        $iotResp->assertStatus(403);
    }

    public function test_petani_can_manage_own_garden_and_assign_garden_laborer(): void
    {
        $farmer = Farmer::create([
            'name' => 'Pak Tani Pekerja',
            'phone' => '08999999999',
        ]);

        // 1. Petani can update own garden via gardens.manage
        $updateResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->putJson('/api/v1/gardens/'.$this->garden->id, [
                'name' => 'Kebun Petani Update Sah',
                'block_id' => $this->block->id,
                'area_hectares' => 4.5,
            ]);
        $updateResp->assertStatus(200);

        // 2. Petani can assign laborer to own garden
        $assignResp = $this->withHeader('Authorization', 'Bearer '.$this->petaniToken)
            ->postJson('/api/v1/gardens/'.$this->garden->id.'/farmers', [
                'farmer_id' => $farmer->id,
                'assignment_date' => now()->toDateString(),
            ]);
        $assignResp->assertStatus(201);
    }

    public function test_administrator_retains_full_administrative_privileges(): void
    {
        // Admin can create a village
        $villageResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/villages', [
                'name' => 'Desa Admin Sah',
                'district' => 'Cikidang',
                'regency' => 'Sukabumi',
                'province' => 'Jawa Barat',
            ]);
        $villageResp->assertStatus(201);

        // Admin can create a durian variety
        $varietyResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/durian-varieties', [
                'name' => 'Varietas Admin Sah',
                'code' => 'VAS',
            ]);
        $varietyResp->assertStatus(201);
    }
}
