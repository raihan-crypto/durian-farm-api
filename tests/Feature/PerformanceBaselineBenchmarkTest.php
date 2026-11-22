<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Role;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerformanceBaselineBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Garden $garden;

    protected Tree $sampleTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->user = User::create([
            'name' => 'Benchmark Admin',
            'email' => 'benchmark@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach($adminRole->id);
        $this->token = $this->user->createToken('bench-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Benchmark']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok Benchmark']);
        $this->garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Benchmark']);
        $variety = DurianVariety::create(['code' => 'OC', 'name' => 'Ochee / D200', 'is_active' => true]);

        $season = HarvestSeason::create([
            'garden_id' => $this->garden->id,
            'name' => 'Musang Season 2026',
            'start_date' => Carbon::now()->subMonths(2)->toDateString(),
            'status' => 'active',
        ]);

        $session = HarvestSession::create([
            'season_id' => $season->id,
            'garden_id' => $this->garden->id,
            'harvest_date' => Carbon::now()->subDays(5),
            'session_name' => 'Sesi Utama',
            'recorded_by' => $this->user->id,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $tree = Tree::create([
                'garden_id' => $this->garden->id,
                'variety_id' => $variety->id,
                'tree_code' => sprintf('BNCH-%03d', $i),
                'tree_name' => "Pohon Bench {$i}",
                'status' => 'active',
                'planted_at' => Carbon::now()->subYears(3)->toDateString(),
            ]);

            TreeMeasurement::create([
                'tree_id' => $tree->id,
                'measured_at' => Carbon::now()->subDays(10),
                'diameter_cm' => 25.5,
                'height_m' => 4.2,
                'recorded_by' => $this->user->id,
            ]);

            HarvestRecord::create([
                'session_id' => $session->id,
                'garden_id' => $this->garden->id,
                'tree_id' => $tree->id,
                'fruit_count' => 12,
                'total_weight_kg' => 24.5,
            ]);

            if ($i === 1) {
                $this->sampleTree = $tree;
            }
        }
    }

    public function test_profile_endpoints_baseline_and_cached(): void
    {
        $endpoints = [
            'Dashboard Summary (Cold)' => ['GET', '/api/v1/dashboard/summary'],
            'Dashboard Summary (Warm)' => ['GET', '/api/v1/dashboard/summary'],
            'Tree Listing' => ['GET', '/api/v1/trees?per_page=15'],
            'Tree Show Detail' => ['GET', "/api/v1/trees/{$this->sampleTree->id}"],
            'Tree History' => ['GET', "/api/v1/trees/{$this->sampleTree->id}/history"],
            'Sync Bootstrap (Cold)' => ['GET', '/api/v1/sync/bootstrap'],
            'Sync Bootstrap (Warm)' => ['GET', '/api/v1/sync/bootstrap'],
            'Durian Varieties' => ['GET', '/api/v1/durian-varieties'],
            'Gardens Listing' => ['GET', '/api/v1/gardens'],
        ];

        $results = [];

        foreach ($endpoints as $label => [$method, $uri]) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $memBefore = memory_get_usage();
            $t0 = microtime(true);

            $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
                ->json($method, $uri);

            $durationMs = (microtime(true) - $t0) * 1000;
            $memDeltaKb = (memory_get_usage() - $memBefore) / 1024;
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            $response->assertStatus(200);

            $results[$label] = [
                'status' => $response->status(),
                'duration_ms' => round($durationMs, 2),
                'queries_count' => count($queries),
                'mem_delta_kb' => round($memDeltaKb, 2),
            ];
        }

        echo "\n=== PERFORMANCE BENCHMARK (COLD VS WARM) ===\n";
        echo sprintf("%-26s | %-12s | %-12s | %-12s\n", 'Endpoint', 'Duration', 'Query Count', 'Memory Delta');
        echo str_repeat('-', 72)."\n";
        foreach ($results as $label => $metric) {
            echo sprintf(
                "%-26s | %8.2f ms | %10d q | %9.2f KB\n",
                $label,
                $metric['duration_ms'],
                $metric['queries_count'],
                $metric['mem_delta_kb']
            );
        }
        echo "============================================\n";

        $this->assertTrue(true);
    }
}
