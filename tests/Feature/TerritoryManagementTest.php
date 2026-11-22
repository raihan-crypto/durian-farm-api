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

class TerritoryManagementTest extends TestCase
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
            'name' => 'Admin Wilayah',
            'email' => 'admin.territory@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    public function test_admin_can_crud_villages_and_get_hierarchy_counts(): void
    {
        $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/villages', [
                'name' => 'Desa Sukamaju',
                'district' => 'Kecamatan Cibadak',
                'regency' => 'Kabupaten Sukabumi',
                'province' => 'Jawa Barat',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Desa Sukamaju',
                    'blocks_count' => 0,
                    'gardens_count' => 0,
                    'trees_count' => 0,
                ],
            ]);

        $villageId = $createResponse->json('data.id');

        $block = Block::create([
            'village_id' => $villageId,
            'name' => 'Blok Utara',
            'area_hectares' => 12.5,
        ]);

        $garden = Garden::create([
            'block_id' => $block->id,
            'name' => 'Kebun Musang King 1',
            'area_hectares' => 4.2,
        ]);

        $variety = DurianVariety::create(['name' => 'Musang King']);

        Tree::create([
            'garden_id' => $garden->id,
            'variety_id' => $variety->id,
            'tree_code' => 'TR-001',
            'status' => 'active',
        ]);
        Tree::create([
            'garden_id' => $garden->id,
            'variety_id' => $variety->id,
            'tree_code' => 'TR-002',
            'status' => 'active',
        ]);

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/villages?search=Sukamaju');

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.items.0.blocks_count', 1)
            ->assertJsonPath('data.items.0.gardens_count', 1)
            ->assertJsonPath('data.items.0.trees_count', 2);

        $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/villages/{$villageId}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.blocks_count', 1)
            ->assertJsonPath('data.gardens_count', 1)
            ->assertJsonPath('data.trees_count', 2)
            ->assertJsonPath('data.blocks.0.trees_count', 2);

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/villages/{$villageId}", [
                'name' => 'Desa Sukamaju Baru',
                'district' => 'Kecamatan Cibadak',
                'regency' => 'Kabupaten Sukabumi',
                'province' => 'Jawa Barat',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Desa Sukamaju Baru');

        // Cannot delete village that still has blocks
        $deleteBlocked = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/villages/{$villageId}");

        $deleteBlocked->assertStatus(422)
            ->assertJsonPath('success', false);

        // Deleting an empty village succeeds
        $emptyVillage = Village::create(['name' => 'Desa Kosong']);

        $deleteSuccess = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/villages/{$emptyVillage->id}");

        $deleteSuccess->assertStatus(200);
        $this->assertSoftDeleted('villages', ['id' => $emptyVillage->id]);
    }

    public function test_admin_can_crud_blocks_and_prevent_deleting_block_with_gardens(): void
    {
        $village = Village::create(['name' => 'Desa Cikembang']);

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/blocks', [
                'village_id' => $village->id,
                'name' => 'Blok A',
                'area_hectares' => 15.75,
                'description' => 'Blok lereng timur',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Blok A')
            ->assertJsonPath('data.village.name', 'Desa Cikembang');

        $blockId = $createResponse->json('data.id');

        Garden::create([
            'block_id' => $blockId,
            'name' => 'Kebun A1',
        ]);

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/blocks?village_id={$village->id}");

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.items.0.gardens_count', 1);

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/blocks/{$blockId}", [
                'village_id' => $village->id,
                'name' => 'Blok A Revisi',
                'area_hectares' => 16.0,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Blok A Revisi');

        $deleteBlocked = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/blocks/{$blockId}");

        $deleteBlocked->assertStatus(422);

        $emptyBlock = Block::create([
            'village_id' => $village->id,
            'name' => 'Blok Kosong',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/blocks/{$emptyBlock->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('blocks', ['id' => $emptyBlock->id]);
    }

    public function test_admin_can_crud_gardens_with_inline_multi_owners_and_farmers(): void
    {
        $village = Village::create(['name' => 'Desa Caringin']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok C1']);

        $owner1 = Owner::create(['owner_type' => 'individual', 'name' => 'Haji Deden']);
        $owner2 = Owner::create(['owner_type' => 'organization', 'name' => 'Koperasi Durian Sejahtera']);
        $farmer = Farmer::create(['name' => 'Kang Ujang', 'phone' => '081234567890']);

        // Reject when active ownership percentages exceed 100%
        $invalidResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/gardens', [
                'block_id' => $block->id,
                'name' => 'Kebun Invalid',
                'owners' => [
                    ['owner_id' => $owner1->id, 'ownership_percentage' => 70, 'start_date' => '2026-01-01'],
                    ['owner_id' => $owner2->id, 'ownership_percentage' => 40, 'start_date' => '2026-01-01'],
                ],
            ]);

        $invalidResponse->assertStatus(422)
            ->assertJsonPath('success', false);

        // Valid multi-owner + farmer creation
        $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/gardens', [
                'block_id' => $block->id,
                'name' => 'Kebun Bawor Utama',
                'area_hectares' => 3.5,
                'address' => 'Jl. Raya Perkebunan No. 1',
                'latitude' => -6.9174639,
                'longitude' => 107.6191228,
                'owners' => [
                    ['owner_id' => $owner1->id, 'ownership_percentage' => 60, 'start_date' => '2026-01-01'],
                    ['owner_id' => $owner2->id, 'ownership_percentage' => 40, 'start_date' => '2026-01-01'],
                ],
                'farmers' => [
                    ['farmer_id' => $farmer->id, 'assignment_date' => '2026-01-10'],
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Kebun Bawor Utama')
            ->assertJsonCount(2, 'data.owners')
            ->assertJsonCount(1, 'data.farmer_assignments');

        $gardenId = $createResponse->json('data.id');

        // Filter gardens by village_id, owner_id, and farmer_id
        $filtered = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/gardens?village_id={$village->id}&owner_id={$owner1->id}&farmer_id={$farmer->id}");

        $filtered->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $gardenId);

        // Prevent deleting garden with trees
        $variety = DurianVariety::create(['name' => 'Bawor']);
        $tree = Tree::create([
            'garden_id' => $gardenId,
            'variety_id' => $variety->id,
            'tree_code' => 'BW-001',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/gardens/{$gardenId}")
            ->assertStatus(422);

        $tree->delete();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/gardens/{$gardenId}")
            ->assertStatus(200);

        $this->assertSoftDeleted('gardens', ['id' => $gardenId]);
    }

    public function test_farmer_territory_access_is_strictly_scoped_to_active_assignments(): void
    {
        $villageA = Village::create(['name' => 'Desa A']);
        $villageB = Village::create(['name' => 'Desa B']);
        $villageC = Village::create(['name' => 'Desa C Tanpa Akses']);

        $blockA1 = Block::create(['village_id' => $villageA->id, 'name' => 'Blok A1']);
        $blockB1 = Block::create(['village_id' => $villageB->id, 'name' => 'Blok B1']);
        $blockB2 = Block::create(['village_id' => $villageB->id, 'name' => 'Blok B2']);
        Block::create(['village_id' => $villageC->id, 'name' => 'Blok C1']);

        $gardenA1_1 = Garden::create(['block_id' => $blockA1->id, 'name' => 'Kebun A1-1']);
        $gardenA1_2 = Garden::create(['block_id' => $blockA1->id, 'name' => 'Kebun A1-2']);
        $gardenB1_1 = Garden::create(['block_id' => $blockB1->id, 'name' => 'Kebun B1-1']);
        $gardenB1_2 = Garden::create(['block_id' => $blockB1->id, 'name' => 'Kebun B1-2']);
        $gardenB2_1 = Garden::create(['block_id' => $blockB2->id, 'name' => 'Kebun B2-1 Expired']);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Lapangan',
            'email' => 'petani.scope@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'name' => 'Petani Lapangan',
        ]);

        // Active assignment to entire Block A1 (gives access to gardenA1_1 & gardenA1_2)
        BlockFarmer::create([
            'block_id' => $blockA1->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(10)->toDateString(),
            'end_date' => null,
        ]);

        // Active direct assignment to Garden B1-1 only (NOT Garden B1-2)
        GardenFarmer::create([
            'garden_id' => $gardenB1_1->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        // Expired direct assignment to Garden B2-1 (must NOT grant access)
        GardenFarmer::create([
            'garden_id' => $gardenB2_1->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(60)->toDateString(),
            'end_date' => Carbon::yesterday()->toDateString(),
        ]);

        // Gardens listing: only 3 accessible gardens
        $gardensResp = $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/gardens');

        $gardensResp->assertStatus(200)
            ->assertJsonCount(3, 'data.items');

        $returnedGardenIds = collect($gardensResp->json('data.items'))->pluck('id')->all();
        $this->assertContains($gardenA1_1->id, $returnedGardenIds);
        $this->assertContains($gardenA1_2->id, $returnedGardenIds);
        $this->assertContains($gardenB1_1->id, $returnedGardenIds);
        $this->assertNotContains($gardenB1_2->id, $returnedGardenIds);
        $this->assertNotContains($gardenB2_1->id, $returnedGardenIds);

        // Direct show checks
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/gardens/{$gardenA1_1->id}")
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/gardens/{$gardenB1_2->id}")
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/gardens/{$gardenB2_1->id}")
            ->assertStatus(403);

        // Blocks listing: only Block A1 and Block B1
        $blocksResp = $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/blocks');

        $blocksResp->assertStatus(200)
            ->assertJsonCount(2, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/blocks/{$blockB2->id}")
            ->assertStatus(403);

        // Villages listing: only Village A and Village B
        $villagesResp = $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/villages');

        $villagesResp->assertStatus(200)
            ->assertJsonCount(2, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/villages/{$villageC->id}")
            ->assertStatus(403);
    }

    public function test_farmer_cannot_manage_territory_endpoints(): void
    {
        $village = Village::create(['name' => 'Desa Protected']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok Protected']);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Biasa',
            'email' => 'petani.denied@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/villages', ['name' => 'Desa Ilegal'])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/blocks', ['village_id' => $village->id, 'name' => 'Blok Ilegal'])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/gardens', ['block_id' => $block->id, 'name' => 'Kebun Ilegal'])
            ->assertStatus(403);
    }
}
