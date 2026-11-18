<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\HarvestDetail;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HarvestReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected DurianVariety $musangKing;

    protected DurianVariety $bawor;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->adminUser = User::create([
            'name' => 'Admin Laporan',
            'email' => 'admin.reports@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Produktif']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok 1']);
        $this->gardenA = Garden::create(['block_id' => $block->id, 'name' => 'Kebun MK']);
        $this->gardenB = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Bawor']);

        $this->musangKing = DurianVariety::create(['code' => 'MK', 'name' => 'Musang King']);
        $this->bawor = DurianVariety::create(['code' => 'BW', 'name' => 'Bawor']);

        $treeMK = Tree::create(['garden_id' => $this->gardenA->id, 'variety_id' => $this->musangKing->id, 'tree_code' => 'MK-01']);
        $treeBW = Tree::create(['garden_id' => $this->gardenB->id, 'variety_id' => $this->bawor->id, 'tree_code' => 'BW-01']);

        // Period A (June 2026)
        $seasonA = HarvestSeason::create(['garden_id' => $this->gardenA->id, 'name' => 'Musim 1', 'start_date' => '2026-06-01']);
        $sessionA = HarvestSession::create([
            'season_id' => $seasonA->id,
            'garden_id' => $this->gardenA->id,
            'harvest_date' => '2026-06-15 08:00:00',
            'session_name' => 'Panen Juni',
        ]);
        $recordA = HarvestRecord::create([
            'session_id' => $sessionA->id,
            'garden_id' => $this->gardenA->id,
            'tree_id' => $treeMK->id,
            'fruit_count' => 10,
            'total_weight_kg' => 20.0,
        ]);
        HarvestDetail::create(['harvest_record_id' => $recordA->id, 'fruit_grade' => 'Grade A', 'fruit_count' => 8, 'weight_kg' => 17.0]);
        HarvestDetail::create(['harvest_record_id' => $recordA->id, 'fruit_grade' => 'Grade B', 'fruit_count' => 2, 'weight_kg' => 3.0]);

        // Period B (July 2026)
        $seasonB = HarvestSeason::create(['garden_id' => $this->gardenB->id, 'name' => 'Musim 2', 'start_date' => '2026-07-01']);
        $sessionB = HarvestSession::create([
            'season_id' => $seasonB->id,
            'garden_id' => $this->gardenB->id,
            'harvest_date' => '2026-07-20 08:00:00',
            'session_name' => 'Panen Juli',
        ]);
        $recordB = HarvestRecord::create([
            'session_id' => $sessionB->id,
            'garden_id' => $this->gardenB->id,
            'tree_id' => $treeBW->id,
            'fruit_count' => 15,
            'total_weight_kg' => 45.0,
            'notes' => '=HYPERLINK("bad")',
        ]);
        HarvestDetail::create(['harvest_record_id' => $recordB->id, 'fruit_grade' => 'Grade A', 'fruit_count' => 15, 'weight_kg' => 45.0]);
    }

    public function test_can_generate_harvest_summary_grouped_by_variety_and_grade(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/reports/harvest/summary?group_by=variety');

        $response->assertStatus(200)
            ->assertJsonPath('data.group_by', 'variety')
            ->assertJsonPath('data.totals.total_records', 2)
            ->assertJsonPath('data.totals.total_trees_harvested', 2)
            ->assertJsonPath('data.totals.total_fruits', 25)
            ->assertJsonPath('data.totals.total_weight_kg', 65)
            ->assertJsonPath('data.totals.average_weight_per_fruit_kg', 2.6)
            ->assertJsonCount(2, 'data.breakdown')
            ->assertJsonPath('data.breakdown.0.label', 'Bawor')
            ->assertJsonPath('data.breakdown.0.total_weight_kg', 45)
            ->assertJsonPath('data.grade_breakdown.0.fruit_grade', 'Grade A')
            ->assertJsonPath('data.grade_breakdown.0.total_weight_kg', 62);
    }

    public function test_can_compare_harvest_across_two_periods(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/reports/harvest/comparison?period_a_from=2026-06-01&period_a_to=2026-06-30&period_b_from=2026-07-01&period_b_to=2026-07-31');

        $response->assertStatus(200)
            ->assertJsonPath('data.period_a.total_fruits', 10)
            ->assertJsonPath('data.period_a.total_weight_kg', 20)
            ->assertJsonPath('data.period_b.total_fruits', 15)
            ->assertJsonPath('data.period_b.total_weight_kg', 45)
            ->assertJsonPath('data.comparison.fruit_diff', 5)
            ->assertJsonPath('data.comparison.weight_diff_kg', 25)
            ->assertJsonPath('data.comparison.fruit_growth_percent', 50)
            ->assertJsonPath('data.comparison.weight_growth_percent', 125);
    }

    public function test_can_export_harvest_report_csv_with_formula_sanitization(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->get('/api/v1/reports/harvest/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $csv = $response->getContent();
        $this->assertStringContainsString('Musang King', $csv);
        $this->assertStringContainsString('Bawor', $csv);
        // Formula cell must be prefixed with single quote
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
