<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockFarmer;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\Owner;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FarmerOwnerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

        $this->adminUser = User::create([
            'name' => 'Admin Kepemilikan',
            'email' => 'admin.ownership@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    public function test_admin_can_crud_owners_and_manage_multi_ownership_on_garden(): void
    {
        $village = Village::create(['name' => 'Desa Cisaat']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok M1']);
        $garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Bersama']);

        // Create owners
        $owner1Resp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/owners', [
                'owner_type' => 'individual',
                'name' => 'Budi Santoso',
                'phone' => '08111111111',
                'address' => 'Bogor',
            ]);
        $owner1Resp->assertStatus(201)->assertJsonPath('data.owner_type', 'individual');
        $owner1Id = $owner1Resp->json('data.id');

        $owner2Resp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/owners', [
                'owner_type' => 'organization',
                'name' => 'PT Durian Nusantara',
                'phone' => '02188889999',
            ]);
        $owner2Resp->assertStatus(201)->assertJsonPath('data.owner_type', 'organization');
        $owner2Id = $owner2Resp->json('data.id');

        $owner3 = Owner::create(['owner_type' => 'individual', 'name' => 'Siti Aminah']);

        // Filter owners by owner_type
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/owners?owner_type=organization')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $owner2Id);

        // Assign owner 1 (60%) and owner 2 (40%)
        $assign1 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/gardens/{$garden->id}/owners", [
                'owner_id' => $owner1Id,
                'ownership_percentage' => 60,
                'start_date' => '2026-01-01',
            ]);
        $assign1->assertStatus(201);
        $ownership1Id = $assign1->json('data.id');

        // Duplicate (garden_id, owner_id, start_date) rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/gardens/{$garden->id}/owners", [
                'owner_id' => $owner1Id,
                'ownership_percentage' => 20,
                'start_date' => '2026-01-01',
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/gardens/{$garden->id}/owners", [
                'owner_id' => $owner2Id,
                'ownership_percentage' => 40,
                'start_date' => '2026-01-01',
            ])
            ->assertStatus(201);

        // Assigning owner 3 with 10% exceeds 100% active limit -> rejected 422
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/gardens/{$garden->id}/owners", [
                'owner_id' => $owner3->id,
                'ownership_percentage' => 10,
                'start_date' => '2026-02-01',
            ])
            ->assertStatus(422);

        // End owner 1's ownership yesterday so 60% becomes available again
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/gardens/{$garden->id}/owners/{$ownership1Id}", [
                'end_date' => Carbon::yesterday()->toDateString(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Now owner 3 can be assigned 60%
        $assign3 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/gardens/{$garden->id}/owners", [
                'owner_id' => $owner3->id,
                'ownership_percentage' => 60,
                'start_date' => Carbon::today()->toDateString(),
            ]);
        $assign3->assertStatus(201);
        $ownership3Id = $assign3->json('data.id');

        // active_only filter returns only 2 active ownerships (owner 2 + owner 3)
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/gardens/{$garden->id}/owners?active_only=1")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Owner detail includes garden_ownerships
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/owners/{$owner3->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.gardens_count', 1)
            ->assertJsonPath('data.garden_ownerships.0.garden.name', 'Kebun Bersama');

        // Cannot delete owner linked to garden ownership
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/owners/{$owner3->id}")
            ->assertStatus(422);

        // Remove ownership then delete owner succeeds
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/gardens/{$garden->id}/owners/{$ownership3Id}")
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/owners/{$owner3->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('owners', ['id' => $owner3->id]);
    }

    public function test_admin_can_crud_farmers_and_assign_to_blocks_and_gardens(): void
    {
        $village = Village::create(['name' => 'Desa Cibinong']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok F1']);
        $garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun F1-A']);

        $userAccount = User::create([
            'name' => 'Akun Petani 1',
            'email' => 'petani1@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Create farmer linked to user account
        $farmerResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/farmers', [
                'user_id' => $userAccount->id,
                'name' => 'Pak Slamet',
                'phone' => '081355566677',
                'address' => 'Cibinong',
            ]);

        $farmerResp->assertStatus(201)
            ->assertJsonPath('data.user_id', $userAccount->id)
            ->assertJsonPath('data.user.email', 'petani1@durianfarm.test');

        $farmerId = $farmerResp->json('data.id');

        // Duplicate user_id for another farmer is rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/farmers', [
                'user_id' => $userAccount->id,
                'name' => 'Pak Duplikat',
            ])
            ->assertStatus(422);

        // Assign farmer to block
        $blockAssign = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/blocks/{$block->id}/farmers", [
                'farmer_id' => $farmerId,
                'assignment_date' => '2026-01-01',
            ]);
        $blockAssign->assertStatus(201)->assertJsonPath('data.is_active', true);
        $blockAssignId = $blockAssign->json('data.id');

        // Duplicate block assignment with same start date rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/blocks/{$block->id}/farmers", [
                'farmer_id' => $farmerId,
                'assignment_date' => '2026-01-01',
            ])
            ->assertStatus(422);

        // Assign farmer to garden
        $gardenAssign = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/gardens/{$garden->id}/farmers", [
                'farmer_id' => $farmerId,
                'assignment_date' => '2026-01-05',
            ]);
        $gardenAssign->assertStatus(201)->assertJsonPath('data.is_active', true);
        $gardenAssignId = $gardenAssign->json('data.id');

        // Invalid end_date earlier than assignment_date rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/gardens/{$garden->id}/farmers/{$gardenAssignId}", [
                'end_date' => '2025-12-31',
            ])
            ->assertStatus(422);

        // Filter farmers by block_id and has_user_account
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/farmers?block_id={$block->id}&has_user_account=true")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $farmerId);

        // Cannot delete farmer while active assignments exist
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/farmers/{$farmerId}")
            ->assertStatus(422);

        // End both assignments -> now delete succeeds
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/blocks/{$block->id}/farmers/{$blockAssignId}", [
                'end_date' => Carbon::yesterday()->toDateString(),
            ])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/gardens/{$garden->id}/farmers/{$gardenAssignId}")
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/farmers/{$farmerId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('farmers', ['id' => $farmerId]);
    }

    public function test_authenticated_farmer_can_fetch_my_assignments_with_summary(): void
    {
        $village = Village::create(['name' => 'Desa Pelabuhanratu']);
        $block1 = Block::create(['village_id' => $village->id, 'name' => 'Blok Timur']);
        $block2 = Block::create(['village_id' => $village->id, 'name' => 'Blok Barat']);

        $garden1 = Garden::create(['block_id' => $block1->id, 'name' => 'Kebun Timur 1']);
        $garden2 = Garden::create(['block_id' => $block1->id, 'name' => 'Kebun Timur 2']);
        $garden3 = Garden::create(['block_id' => $block2->id, 'name' => 'Kebun Barat 1']);
        $garden4 = Garden::create(['block_id' => $block2->id, 'name' => 'Kebun Barat Expired']);

        $variety = DurianVariety::create(['name' => 'Duri Hitam']);
        foreach ([$garden1, $garden1, $garden2, $garden3, $garden3] as $index => $g) {
            Tree::create([
                'garden_id' => $g->id,
                'variety_id' => $variety->id,
                'tree_code' => 'DH-'.($index + 1),
                'status' => 'active',
            ]);
        }

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Kang Asep',
            'email' => 'asep@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('mobile-app')->plainTextToken;

        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'name' => 'Kang Asep',
            'phone' => '081900011122',
        ]);

        // Active block assignment to block1 (garden1 + garden2 = 3 trees)
        BlockFarmer::create([
            'block_id' => $block1->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subWeek()->toDateString(),
        ]);

        // Active direct garden assignment to garden3 (2 trees)
        GardenFarmer::create([
            'garden_id' => $garden3->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(3)->toDateString(),
        ]);

        // Expired direct garden assignment to garden4 (excluded)
        GardenFarmer::create([
            'garden_id' => $garden4->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subMonths(2)->toDateString(),
            'end_date' => Carbon::yesterday()->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/my-assignments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farmer.id', $farmer->id)
            ->assertJsonCount(1, 'data.assigned_blocks')
            ->assertJsonCount(1, 'data.assigned_gardens')
            ->assertJsonCount(3, 'data.accessible_gardens')
            ->assertJsonPath('data.summary.total_assigned_blocks', 1)
            ->assertJsonPath('data.summary.total_direct_gardens', 1)
            ->assertJsonPath('data.summary.total_accessible_gardens', 3)
            ->assertJsonPath('data.summary.total_accessible_trees', 5);
    }

    public function test_user_without_farmer_profile_gets_empty_my_assignments(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/my-assignments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farmer', null)
            ->assertJsonPath('data.summary.total_accessible_gardens', 0);
    }

    public function test_unauthorized_role_cannot_access_owners_and_farmers_management(): void
    {
        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Terbatas',
            'email' => 'petani.terbatas@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/owners')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/owners', ['name' => 'Owner Ilegal'])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/farmers')
            ->assertStatus(403);
    }
}
