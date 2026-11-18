<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Role;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TreeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Village $village;

    protected Block $block;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected DurianVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

        $this->adminUser = User::create([
            'name' => 'Admin Pohon',
            'email' => 'admin.trees@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->village = Village::create(['name' => 'Desa Sukasari', 'district' => 'Cibadak', 'regency' => 'Sukabumi', 'province' => 'Jawa Barat']);
        $this->block = Block::create(['village_id' => $this->village->id, 'name' => 'Blok Utama']);
        $this->gardenA = Garden::create(['block_id' => $this->block->id, 'name' => 'Kebun A']);
        $this->gardenB = Garden::create(['block_id' => $this->block->id, 'name' => 'Kebun B']);
        $this->variety = DurianVariety::create(['code' => 'MK', 'name' => 'Musang King', 'is_active' => true]);
    }

    public function test_admin_can_crud_trees_with_extended_statuses_and_auto_qr_code(): void
    {
        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/trees', [
                'garden_id' => $this->gardenA->id,
                'variety_id' => $this->variety->id,
                'tree_code' => 'MK-A-001',
                'tree_name' => 'Musang King Induk 1',
                'planted_at' => '2023-05-10',
                'initial_diameter_cm' => 12.5,
                'initial_height_m' => 2.8,
                'initial_canopy_width_m' => 1.9,
                'latitude' => -6.8851234,
                'longitude' => 106.7745678,
                'status' => 'monitoring',
                'notes' => 'Pertumbuhan bagus',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tree_code', 'MK-A-001')
            ->assertJsonPath('data.status', 'monitoring')
            ->assertJsonPath('data.status_label', 'Dalam pemantauan')
            ->assertJsonPath('data.qr_code.is_active', true);

        $treeId = $createResp->json('data.id');
        $this->assertDatabaseHas('tree_qr_codes', ['tree_id' => $treeId, 'is_active' => true]);

        // Duplicate tree_code in the same garden rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/trees', [
                'garden_id' => $this->gardenA->id,
                'tree_code' => 'MK-A-001',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tree_code']);

        // Same tree_code in a different garden is allowed
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/trees', [
                'garden_id' => $this->gardenB->id,
                'tree_code' => 'MK-A-001',
                'status' => 'sick',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status_label', 'Sakit');

        // Filter & statuses meta
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees?garden_id={$this->gardenA->id}&status=monitoring&search=Induk")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $treeId);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/trees/statuses')
            ->assertStatus(200)
            ->assertJsonCount(count(Tree::STATUS_LABELS), 'data');

        // Update status to unproductive
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/trees/{$treeId}", [
                'garden_id' => $this->gardenA->id,
                'variety_id' => $this->variety->id,
                'tree_code' => 'MK-A-001',
                'status' => 'unproductive',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'unproductive');

        // Delete tree without history succeeds
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/trees/{$treeId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('trees', ['id' => $treeId]);
    }

    public function test_historical_measurements_and_tree_photos_preserve_history(): void
    {
        Storage::fake('public');

        $tree = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $this->variety->id,
            'tree_code' => 'MK-M-001',
            'status' => 'active',
        ]);

        // Reject measurement when all metrics are omitted
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/tree-measurements', [
                'tree_id' => $tree->id,
            ])
            ->assertStatus(422);

        // First measurement (older)
        $m1 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/tree-measurements', [
                'tree_id' => $tree->id,
                'measured_at' => '2026-01-15 08:00:00',
                'diameter_cm' => 15.0,
                'height_m' => 3.2,
                'canopy_width_m' => 2.4,
                'notes' => 'Pengukuran awal tahun',
            ]);
        $m1->assertStatus(201);

        // Second measurement (newer) with photo
        $m2 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post('/api/v1/tree-measurements', [
                'tree_id' => $tree->id,
                'measured_at' => '2026-06-15 09:30:00',
                'diameter_cm' => 18.5,
                'height_m' => 3.9,
                'canopy_width_m' => 3.1,
                'photos' => [UploadedFile::fake()->image('ukur-juni.jpg', 500, 500)],
            ], ['Accept' => 'application/json']);
        $m2->assertStatus(201)
            ->assertJsonCount(1, 'data.photos');

        // Both measurements remain stored (newest first)
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees/{$tree->id}/measurements")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.diameter_cm', 18.5)
            ->assertJsonPath('data.items.1.diameter_cm', 15);

        // Tree detail shows latest measurement and total measurements count
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees/{$tree->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.measurements_count', 2)
            ->assertJsonPath('data.latest_measurement.diameter_cm', 18.5);

        // Upload & delete tree photo
        $photoResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post("/api/v1/trees/{$tree->id}/photos", [
                'photo' => UploadedFile::fake()->image('pohon.png', 400, 400),
                'caption' => 'Tampak depan',
            ], ['Accept' => 'application/json']);

        $photoResp->assertStatus(201)->assertJsonPath('data.caption', 'Tampak depan');
        $photoId = $photoResp->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees/{$tree->id}/photos")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/trees/{$tree->id}/photos/{$photoId}")
            ->assertStatus(200);

        // Tree with measurement history cannot be deleted
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/trees/{$tree->id}")
            ->assertStatus(422);
    }

    public function test_tree_history_endpoint_aggregates_all_chronological_records(): void
    {
        $tree = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $this->variety->id,
            'tree_code' => 'MK-H-001',
            'status' => 'active',
        ]);

        TreeMeasurement::create([
            'tree_id' => $tree->id,
            'measured_at' => '2026-01-10 08:00:00',
            'diameter_cm' => 20.5,
            'recorded_by' => $this->adminUser->id,
        ]);

        FertilizationRecord::create([
            'tree_id' => $tree->id,
            'activity_date' => '2026-02-01 07:00:00',
            'method' => 'tabur',
            'notes' => 'NPK 16-16-16',
            'recorded_by' => $this->adminUser->id,
        ]);

        SprayingRecord::create([
            'tree_id' => $tree->id,
            'activity_date' => '2026-02-15 06:30:00',
            'spraying_method' => 'kabut',
            'recorded_by' => $this->adminUser->id,
        ]);

        FloweringRecord::create([
            'tree_id' => $tree->id,
            'flowering_date' => '2026-03-01',
            'flowering_status' => 'berbunga',
            'flower_count' => 120,
            'recorded_by' => $this->adminUser->id,
        ]);

        FruitDevelopmentRecord::create([
            'tree_id' => $tree->id,
            'observation_date' => '2026-04-20',
            'fruit_count' => 35,
            'development_stage' => 'pembesaran',
            'recorded_by' => $this->adminUser->id,
        ]);

        $season = HarvestSeason::create([
            'garden_id' => $this->gardenA->id,
            'name' => 'Musim Panen 2026',
            'start_date' => '2026-06-01',
        ]);
        $session = HarvestSession::create([
            'season_id' => $season->id,
            'garden_id' => $this->gardenA->id,
            'harvest_date' => '2026-07-05 09:00:00',
            'session_name' => 'Panen Pagi 1',
            'recorded_by' => $this->adminUser->id,
        ]);
        HarvestRecord::create([
            'session_id' => $session->id,
            'garden_id' => $this->gardenA->id,
            'tree_id' => $tree->id,
            'fruit_count' => 12,
            'total_weight_kg' => 24.5,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees/{$tree->id}/history");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.measurements')
            ->assertJsonCount(1, 'data.fertilizations')
            ->assertJsonCount(1, 'data.sprayings')
            ->assertJsonCount(1, 'data.flowerings')
            ->assertJsonCount(1, 'data.fruit_developments')
            ->assertJsonCount(1, 'data.harvests')
            ->assertJsonCount(6, 'data.timeline')
            ->assertJsonPath('data.timeline.0.type', 'harvest')
            ->assertJsonPath('data.timeline.5.type', 'measurement');
    }

    public function test_farmer_and_unlinked_owner_tree_access_is_strictly_scoped(): void
    {
        $treeA = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $this->variety->id,
            'tree_code' => 'A-01',
            'status' => 'active',
        ]);
        $treeB = Tree::create([
            'garden_id' => $this->gardenB->id,
            'variety_id' => $this->variety->id,
            'tree_code' => 'B-01',
            'status' => 'active',
        ]);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Kebun A',
            'email' => 'petani.tree@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'name' => 'Petani Kebun A']);
        GardenFarmer::create([
            'garden_id' => $this->gardenA->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        // Farmer only sees treeA
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/trees')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $treeA->id);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/trees/{$treeB->id}")
            ->assertStatus(403);

        // Farmer can record measurement on assigned treeA, denied on treeB
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/tree-measurements', [
                'tree_id' => $treeA->id,
                'height_m' => 4.1,
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/tree-measurements', [
                'tree_id' => $treeB->id,
                'height_m' => 4.1,
            ])
            ->assertStatus(403);
    }

    public function test_unlinked_owner_tree_access_is_denied_by_default(): void
    {
        $treeA = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $this->variety->id,
            'tree_code' => 'A-01',
            'status' => 'active',
        ]);

        // Owner-only user without link is denied by default (sees 0 trees)
        $ownerRole = Role::where('name', 'owner')->firstOrFail();
        $ownerUser = User::create([
            'name' => 'Pemilik Belum Terhubung',
            'email' => 'owner.unlinked@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $ownerUser->roles()->attach($ownerRole->id);
        $ownerToken = $ownerUser->createToken('owner-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->getJson('/api/v1/trees')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->getJson("/api/v1/trees/{$treeA->id}")
            ->assertStatus(403);
    }
}
