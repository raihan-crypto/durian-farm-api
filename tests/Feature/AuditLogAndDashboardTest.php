<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected string $superAdminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->superAdmin = User::create([
            'name' => 'Super Admin Audit',
            'email' => 'super.audit@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id);
        $this->superAdminToken = $this->superAdmin->createToken('super-token')->plainTextToken;
    }

    public function test_auditable_models_record_create_update_delete_and_exclude_passwords(): void
    {
        $villageResp = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
            ->postJson('/api/v1/villages', ['name' => 'Desa Audit Awal']);
        $villageResp->assertStatus(201);
        $villageId = $villageResp->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
            ->putJson("/api/v1/villages/{$villageId}", ['name' => 'Desa Audit Diubah'])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
            ->deleteJson("/api/v1/villages/{$villageId}")
            ->assertStatus(200);

        // Verify all 3 audit actions exist for the village
        $logsResp = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
            ->getJson("/api/v1/audit-logs?table_name=villages&record_id={$villageId}");

        $logsResp->assertStatus(200)
            ->assertJsonCount(3, 'data.items');

        $updateLog = AuditLog::query()
            ->where('table_name', 'villages')
            ->where('record_id', $villageId)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('Desa Audit Awal', $updateLog->old_values['name']);
        $this->assertSame('Desa Audit Diubah', $updateLog->new_values['name']);
        $this->assertSame($this->superAdmin->id, $updateLog->user_id);

        // Password must never be logged for User creations
        $userLog = AuditLog::query()
            ->where('table_name', 'users')
            ->where('record_id', $this->superAdmin->id)
            ->firstOrFail();
        $this->assertArrayNotHasKey('password', $userLog->new_values ?? []);
    }

    public function test_non_super_admin_cannot_view_audit_logs(): void
    {
        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Biasa',
            'email' => 'petani.noaudit@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    public function test_dashboard_summary_returns_territory_scoped_metrics(): void
    {
        $village = Village::create(['name' => 'Desa Dashboard']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok D1']);
        $gardenA = Garden::create(['block_id' => $block->id, 'name' => 'Kebun D-A']);
        $gardenB = Garden::create(['block_id' => $block->id, 'name' => 'Kebun D-B']);

        $mk = DurianVariety::create(['code' => 'MK', 'name' => 'Musang King']);
        $treeA = Tree::create(['garden_id' => $gardenA->id, 'variety_id' => $mk->id, 'tree_code' => 'DA-01', 'status' => 'active']);
        Tree::create(['garden_id' => $gardenB->id, 'variety_id' => $mk->id, 'tree_code' => 'DB-01', 'status' => 'sick']);

        $season = HarvestSeason::create(['garden_id' => $gardenA->id, 'name' => 'Musim D', 'start_date' => '2026-06-01']);
        $session = HarvestSession::create(['season_id' => $season->id, 'garden_id' => $gardenA->id, 'harvest_date' => now()]);
        HarvestRecord::create([
            'session_id' => $session->id,
            'garden_id' => $gardenA->id,
            'tree_id' => $treeA->id,
            'fruit_count' => 7,
            'total_weight_kg' => 15.5,
        ]);

        // Super admin sees both gardens and both trees
        $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertStatus(200)
            ->assertJsonPath('data.territory.gardens_count', 2)
            ->assertJsonPath('data.territory.trees_count', 2)
            ->assertJsonPath('data.harvest_totals.total_fruits', 7)
            ->assertJsonPath('data.harvest_totals.total_weight_kg', 15.5);

        // Farmer assigned only to Garden A sees only 1 garden and 1 tree
        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Dashboard',
            'email' => 'petani.dash@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-dash')->plainTextToken;

        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'name' => 'Petani Dashboard']);
        GardenFarmer::create([
            'garden_id' => $gardenA->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->toDateString(),
        ]);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertStatus(200)
            ->assertJsonPath('data.territory.gardens_count', 1)
            ->assertJsonPath('data.territory.trees_count', 1)
            ->assertJsonPath('data.harvest_totals.total_fruits', 7);
    }
}
