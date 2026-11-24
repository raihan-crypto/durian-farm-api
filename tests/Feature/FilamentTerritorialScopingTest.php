<?php

namespace Tests\Feature;

use App\Filament\Resources\Blocks\BlockResource;
use App\Filament\Resources\Gardens\GardenResource;
use App\Filament\Resources\HarvestRecords\HarvestRecordResource;
use App\Filament\Resources\Trees\TreeResource;
use App\Filament\Resources\Villages\VillageResource;
use App\Filament\Widgets\PlantationStatsOverview;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\GardenOwner;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FilamentTerritorialScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $pemerintahDesa;

    protected User $petaniA;

    protected User $petaniB;

    protected Village $villageA;

    protected Village $villageB;

    protected Block $blockA;

    protected Block $blockB;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected Tree $treeA;

    protected Tree $treeB;

    protected HarvestSession $sessionA;

    protected HarvestSession $sessionB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $org = Organization::create(['name' => 'PT Perkebunan Uji Scoping']);

        // 1. Roles
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $desaRole = Role::where('name', 'pemerintah_desa')->firstOrFail();
        $petaniRole = Role::where('name', 'petani')->firstOrFail();

        // 2. Villages & Blocks
        $this->villageA = Village::create([
            'name' => 'Desa A Banyumas',
            'district' => 'Kecamatan A',
            'regency' => 'Banyumas',
            'province' => 'Jawa Tengah',
        ]);
        $this->villageB = Village::create([
            'name' => 'Desa B Cilacap',
            'district' => 'Kecamatan B',
            'regency' => 'Cilacap',
            'province' => 'Jawa Tengah',
        ]);

        $this->blockA = Block::create([
            'village_id' => $this->villageA->id,
            'name' => 'Blok A1',
            'approval_status' => 'approved',
        ]);
        $this->blockB = Block::create([
            'village_id' => $this->villageB->id,
            'name' => 'Blok B1',
            'approval_status' => 'approved',
        ]);

        // 3. Gardens
        $this->gardenA = Garden::create([
            'block_id' => $this->blockA->id,
            'name' => 'Kebun Petani A',
            'status' => 'active',
        ]);
        $this->gardenB = Garden::create([
            'block_id' => $this->blockB->id,
            'name' => 'Kebun Petani B',
            'status' => 'active',
        ]);

        // 4. Users
        $this->superAdmin = User::create([
            'organization_id' => $org->id,
            'name' => 'Super Administrator',
            'email' => 'superadmin.scoping@test.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id);

        $this->pemerintahDesa = User::create([
            'organization_id' => $org->id,
            'village_id' => $this->villageA->id,
            'name' => 'Perangkat Desa A',
            'email' => 'desa.a@test.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->pemerintahDesa->roles()->attach($desaRole->id);

        $this->petaniA = User::create([
            'organization_id' => $org->id,
            'name' => 'Petani A',
            'email' => 'petani.a@test.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->petaniA->roles()->attach($petaniRole->id);
        $ownerA = Owner::create(['user_id' => $this->petaniA->id, 'name' => 'Petani A', 'owner_type' => 'individual']);
        GardenOwner::create([
            'garden_id' => $this->gardenA->id,
            'owner_id' => $ownerA->id,
            'ownership_percentage' => 100,
            'start_date' => now()->toDateString(),
        ]);

        $this->petaniB = User::create([
            'organization_id' => $org->id,
            'name' => 'Petani B',
            'email' => 'petani.b@test.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->petaniB->roles()->attach($petaniRole->id);
        $ownerB = Owner::create(['user_id' => $this->petaniB->id, 'name' => 'Petani B', 'owner_type' => 'individual']);
        GardenOwner::create([
            'garden_id' => $this->gardenB->id,
            'owner_id' => $ownerB->id,
            'ownership_percentage' => 100,
            'start_date' => now()->toDateString(),
        ]);

        // 5. Trees & Harvest Records
        $variety = DurianVariety::create(['name' => 'Musang King', 'code' => 'MK', 'is_active' => true]);

        $this->treeA = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $variety->id,
            'tree_code' => 'TREE-A-01',
            'status' => 'active',
        ]);
        $this->treeB = Tree::create([
            'garden_id' => $this->gardenB->id,
            'variety_id' => $variety->id,
            'tree_code' => 'TREE-B-01',
            'status' => 'active',
        ]);

        $seasonA = HarvestSeason::create(['garden_id' => $this->gardenA->id, 'name' => 'Musim 2026 Kebun A', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth()]);
        $seasonB = HarvestSeason::create(['garden_id' => $this->gardenB->id, 'name' => 'Musim 2026 Kebun B', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth()]);

        $this->sessionA = HarvestSession::create([
            'season_id' => $seasonA->id,
            'garden_id' => $this->gardenA->id,
            'harvest_date' => now(),
            'session_name' => 'Panen Kebun A',
        ]);
        HarvestRecord::create([
            'session_id' => $this->sessionA->id,
            'garden_id' => $this->gardenA->id,
            'tree_id' => $this->treeA->id,
            'fruit_count' => 15,
            'total_weight_kg' => 30.5,
        ]);

        $this->sessionB = HarvestSession::create([
            'season_id' => $seasonB->id,
            'garden_id' => $this->gardenB->id,
            'harvest_date' => now(),
            'session_name' => 'Panen Kebun B',
        ]);
        HarvestRecord::create([
            'session_id' => $this->sessionB->id,
            'garden_id' => $this->gardenB->id,
            'tree_id' => $this->treeB->id,
            'fruit_count' => 25,
            'total_weight_kg' => 55.0,
        ]);
    }

    public function test_super_admin_has_unrestricted_scoping_across_all_resources(): void
    {
        $this->actingAs($this->superAdmin);

        $villages = VillageResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->villageA->id, $villages);
        $this->assertContains($this->villageB->id, $villages);

        $blocks = BlockResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->blockA->id, $blocks);
        $this->assertContains($this->blockB->id, $blocks);

        $gardens = GardenResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->gardenA->id, $gardens);
        $this->assertContains($this->gardenB->id, $gardens);

        $trees = TreeResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->treeA->id, $trees);
        $this->assertContains($this->treeB->id, $trees);
    }

    public function test_pemerintah_desa_is_strictly_scoped_to_assigned_village(): void
    {
        $this->actingAs($this->pemerintahDesa);

        // VillageResource query only returns Village A
        $villages = VillageResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->villageA->id, $villages);
        $this->assertNotContains($this->villageB->id, $villages);

        // BlockResource query only returns Block A
        $blocks = BlockResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->blockA->id, $blocks);
        $this->assertNotContains($this->blockB->id, $blocks);

        // GardenResource query only returns Garden A
        $gardens = GardenResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->gardenA->id, $gardens);
        $this->assertNotContains($this->gardenB->id, $gardens);
    }

    public function test_petani_is_strictly_scoped_to_owned_gardens(): void
    {
        $this->actingAs($this->petaniA);

        // GardenResource query only returns Garden A
        $gardens = GardenResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->gardenA->id, $gardens);
        $this->assertNotContains($this->gardenB->id, $gardens);

        // TreeResource query only returns Tree A
        $trees = TreeResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->treeA->id, $trees);
        $this->assertNotContains($this->treeB->id, $trees);

        // HarvestRecordResource query only returns records from Garden A
        $harvests = HarvestRecordResource::getEloquentQuery()->pluck('garden_id')->all();
        $this->assertContains($this->gardenA->id, $harvests);
        $this->assertNotContains($this->gardenB->id, $harvests);
    }

    public function test_dashboard_widgets_render_territorially_scoped_metrics(): void
    {
        // 1. Petani A tests overview stats
        $this->actingAs($this->petaniA);
        $statsWidget = new PlantationStatsOverview;
        $stats = invade($statsWidget)->getStats();

        // Total trees for Petani A should be 1 (treeA only)
        $this->assertStringContainsString('1', $stats[1]->getValue());

        // Total harvest for Petani A should be 30.50 kg
        $this->assertStringContainsString('30.50 kg', $stats[3]->getValue());

        // 2. Petani B tests overview stats
        $this->actingAs($this->petaniB);
        $statsWidgetB = new PlantationStatsOverview;
        $statsB = invade($statsWidgetB)->getStats();

        // Total harvest for Petani B should be 55.00 kg
        $this->assertStringContainsString('55.00 kg', $statsB[3]->getValue());
    }

    public function test_petani_cannot_access_foreign_garden_or_tree_via_filament_query(): void
    {
        $this->actingAs($this->petaniA);

        // Attempting to query foreign gardenB via GardenResource returns empty
        $foreignGarden = GardenResource::getEloquentQuery()->where('id', $this->gardenB->id)->first();
        $this->assertNull($foreignGarden);

        // Attempting to query foreign treeB via TreeResource returns empty
        $foreignTree = TreeResource::getEloquentQuery()->where('id', $this->treeB->id)->first();
        $this->assertNull($foreignTree);
    }
}
