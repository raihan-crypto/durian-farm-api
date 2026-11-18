<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HarvestManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected Tree $treeA1;

    protected Tree $treeB1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->adminUser = User::create([
            'name' => 'Admin Panen',
            'email' => 'admin.harvest@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Panen']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok Panen']);
        $this->gardenA = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Panen A']);
        $this->gardenB = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Panen B']);

        $variety = DurianVariety::create(['code' => 'MK', 'name' => 'Musang King', 'is_active' => true]);
        $this->treeA1 = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $variety->id,
            'tree_code' => 'HA-01',
            'status' => 'active',
        ]);
        $this->treeB1 = Tree::create([
            'garden_id' => $this->gardenB->id,
            'variety_id' => $variety->id,
            'tree_code' => 'HB-01',
            'status' => 'active',
        ]);
    }

    public function test_can_manage_seasons_sessions_and_per_tree_harvest_records_with_grade_details(): void
    {
        Storage::fake('public');

        // Create season on Garden A
        $seasonResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/harvest-seasons', [
                'garden_id' => $this->gardenA->id,
                'name' => 'Musim Panen Raya 2026',
                'start_date' => '2026-06-01',
                'end_date' => '2026-09-30',
                'status' => 'active',
            ]);

        $seasonResp->assertStatus(201)->assertJsonPath('data.name', 'Musim Panen Raya 2026');
        $seasonId = $seasonResp->json('data.id');

        // Reject session with mismatched garden_id
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/harvest-sessions', [
                'season_id' => $seasonId,
                'garden_id' => $this->gardenB->id,
                'harvest_date' => '2026-07-10 08:00:00',
                'session_name' => 'Sesi Salah Kebun',
            ])
            ->assertStatus(422);

        // Valid session (garden_id inferred from season)
        $sessionResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/harvest-sessions', [
                'season_id' => $seasonId,
                'harvest_date' => '2026-07-10 08:00:00',
                'session_name' => 'Panen Pagi 1',
                'weather_condition' => 'Cerah',
            ]);

        $sessionResp->assertStatus(201)
            ->assertJsonPath('data.garden_id', $this->gardenA->id);
        $sessionId = $sessionResp->json('data.id');

        // Composite FK guard: tree from Garden B cannot be recorded into Garden A's session
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/harvest-records', [
                'session_id' => $sessionId,
                'tree_id' => $this->treeB1->id,
                'fruit_count' => 5,
                'total_weight_kg' => 10.5,
            ])
            ->assertStatus(422);

        // Reject details whose fruit_count exceeds header fruit_count
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/harvest-records', [
                'session_id' => $sessionId,
                'tree_id' => $this->treeA1->id,
                'fruit_count' => 5,
                'total_weight_kg' => 10.0,
                'details' => [
                    ['fruit_grade' => 'Grade A', 'fruit_count' => 6, 'weight_kg' => 9.0],
                ],
            ])
            ->assertStatus(422);

        // Valid harvest record with automatic totals from grade details + photo
        $recordResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post('/api/v1/harvest-records', [
                'session_id' => $sessionId,
                'tree_id' => $this->treeA1->id,
                'notes' => 'Buah matang pohon',
                'details' => [
                    ['fruit_grade' => 'Grade A', 'fruit_count' => 8, 'weight_kg' => 18.4],
                    ['fruit_grade' => 'Grade B', 'fruit_count' => 2, 'weight_kg' => 3.6],
                ],
                'photos' => [UploadedFile::fake()->image('panen.jpg', 500, 500)],
            ], ['Accept' => 'application/json']);

        $recordResp->assertStatus(201)
            ->assertJsonPath('data.fruit_count', 10)
            ->assertJsonPath('data.total_weight_kg', 22)
            ->assertJsonPath('data.average_weight_per_fruit_kg', 2.2)
            ->assertJsonCount(2, 'data.details')
            ->assertJsonCount(1, 'data.photos');

        $recordId = $recordResp->json('data.id');

        // Duplicate tree in the same session rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/harvest-records', [
                'session_id' => $sessionId,
                'tree_id' => $this->treeA1->id,
                'fruit_count' => 1,
                'total_weight_kg' => 2.0,
            ])
            ->assertStatus(422);

        // Update record & replace details
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/harvest-records/{$recordId}", [
                'session_id' => $sessionId,
                'tree_id' => $this->treeA1->id,
                'details' => [
                    ['fruit_grade' => 'Grade A', 'fruit_count' => 10, 'weight_kg' => 25.0],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.fruit_count', 10)
            ->assertJsonPath('data.total_weight_kg', 25)
            ->assertJsonCount(1, 'data.details');

        // Season and session with child records cannot be deleted
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/harvest-sessions/{$sessionId}")
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/harvest-seasons/{$seasonId}")
            ->assertStatus(422);
    }

    public function test_farmer_harvest_recording_is_scoped_to_assigned_garden(): void
    {
        $seasonA = HarvestSeason::create([
            'garden_id' => $this->gardenA->id,
            'name' => 'Musim A',
            'start_date' => '2026-06-01',
        ]);
        $sessionA = HarvestSession::create([
            'season_id' => $seasonA->id,
            'garden_id' => $this->gardenA->id,
            'harvest_date' => '2026-07-01 08:00:00',
            'session_name' => 'Sesi A',
        ]);

        $seasonB = HarvestSeason::create([
            'garden_id' => $this->gardenB->id,
            'name' => 'Musim B',
            'start_date' => '2026-06-01',
        ]);
        $sessionB = HarvestSession::create([
            'season_id' => $seasonB->id,
            'garden_id' => $this->gardenB->id,
            'harvest_date' => '2026-07-01 08:00:00',
            'session_name' => 'Sesi B',
        ]);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Panen A',
            'email' => 'petani.harvest@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'name' => 'Petani Panen A']);
        GardenFarmer::create([
            'garden_id' => $this->gardenA->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        // Farmer CAN record harvest on Garden A
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/harvest-records', [
                'session_id' => $sessionA->id,
                'tree_id' => $this->treeA1->id,
                'fruit_count' => 4,
                'total_weight_kg' => 8.8,
            ])
            ->assertStatus(201);

        // Farmer CANNOT record harvest on Garden B
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/harvest-records', [
                'session_id' => $sessionB->id,
                'tree_id' => $this->treeB1->id,
                'fruit_count' => 4,
                'total_weight_kg' => 8.8,
            ])
            ->assertStatus(403);

        // Farmer CANNOT see Garden B's seasons or sessions
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/harvest-seasons')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/harvest-sessions/{$sessionB->id}")
            ->assertStatus(403);
    }
}
