<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityPhoto;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\Garden;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\IotDevice;
use App\Models\MonitoringArea;
use App\Models\Role;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use App\Services\TerritoryScopeService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningVulnRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Storage::fake('public');
    }

    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'User '.Str::random(6),
            'email' => Str::lower(Str::random(8)).'@durian.test',
            'password' => 'Password123!',
            'is_active' => true,
        ], $attributes));

        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh('roles.permissions');
    }

    /**
     * @return array{village: Village, block: Block, garden: Garden}
     */
    private function createTerritory(string $suffix = 'A'): array
    {
        $village = Village::create([
            'name' => "Desa {$suffix}",
            'district' => "Kecamatan {$suffix}",
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);

        $block = Block::create([
            'village_id' => $village->id,
            'name' => "Blok {$suffix}",
            'approval_status' => 'approved',
        ]);

        $garden = Garden::create([
            'block_id' => $block->id,
            'name' => "Kebun {$suffix}",
            'ownership_type' => 'individual',
            'status' => 'active',
        ]);

        return compact('village', 'block', 'garden');
    }

    // =========================================================================
    // VULN-02: Role Hierarchy Protection
    // =========================================================================

    public function test_vuln02_admin_cannot_self_escalate_to_super_admin_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        // Attempt self-escalation via role_names
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$admin->id}", [
                'role_names' => ['super_admin'],
            ])
            ->assertStatus(403);

        // Attempt self-escalation via role_ids
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$admin->id}", [
                'role_ids' => [$superAdminRole->id],
            ])
            ->assertStatus(403);

        // Attempt changing own roles to another role
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$admin->id}", [
                'role_names' => ['petani'],
            ])
            ->assertStatus(403);

        $this->assertFalse($admin->fresh('roles')->hasRole('super_admin'));
        $this->assertTrue($admin->fresh('roles')->hasRole('admin'));
    }

    public function test_vuln02_admin_cannot_assign_super_admin_to_other_users_or_modify_super_admin(): void
    {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = $this->createUserWithRole('super_admin');
        $targetUser = $this->createUserWithRole('petani');
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        // Cannot create a new super_admin
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users', [
                'name' => 'Rogue Super',
                'email' => 'rogue@durian.test',
                'password' => 'Password123!',
                'role_names' => ['super_admin'],
            ])
            ->assertStatus(403);

        // Cannot escalate another user via role_ids
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$targetUser->id}", [
                'role_ids' => [$superAdminRole->id],
            ])
            ->assertStatus(403);

        // Cannot modify an existing super_admin account
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$superAdmin->id}", [
                'name' => 'Hijacked Name',
            ])
            ->assertStatus(403);

        // Cannot deactivate an existing super_admin account
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/users/{$superAdmin->id}")
            ->assertStatus(403);

        $this->assertTrue($superAdmin->fresh()->is_active);
        $this->assertFalse($targetUser->fresh('roles')->hasRole('super_admin'));
    }

    public function test_vuln02_super_admin_retains_legitimate_role_management_and_filament_guards_work(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $admin = $this->createUserWithRole('admin');
        $targetUser = $this->createUserWithRole('petani');
        $token = $superAdmin->createToken('test')->plainTextToken;

        // Super admin CAN promote another user to super_admin
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$targetUser->id}", [
                'role_names' => ['super_admin'],
            ])
            ->assertStatus(200);

        $this->assertTrue($targetUser->fresh('roles')->hasRole('super_admin'));

        // Filament UserResource::canEdit blocks admin from editing super_admin
        $this->actingAs($admin);
        $this->assertFalse(UserResource::canEdit($superAdmin));

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->assertFalse(RoleResource::canEdit($superAdminRole));
        $this->assertFalse(RoleResource::canEdit($adminRole));

        // Filament allows super_admin
        $this->actingAs($superAdmin);
        $this->assertTrue(UserResource::canEdit($admin));
        $this->assertTrue(RoleResource::canEdit($superAdminRole));
    }

    // =========================================================================
    // VULN-03: Delta Sync Completeness (Pagination, Tie-Breaking, Concurrency, Tombstones)
    // =========================================================================

    public function test_vuln03_delta_sync_paginates_over_200_records_with_identical_timestamps_without_skips(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $token = $superAdmin->createToken('test')->plainTextToken;
        $territory = $this->createTerritory('SYNC');

        $baseTime = Carbon::parse('2026-11-24 10:00:00', 'UTC');
        $sinceTime = $baseTime->copy()->subMinute()->toISOString();
        Carbon::setTestNow($baseTime);

        // Insert 205 trees sharing the EXACT same updated_at timestamp
        $expectedIds = [];
        for ($i = 1; $i <= 205; $i++) {
            $tree = Tree::create([
                'garden_id' => $territory['garden']->id,
                'tree_code' => sprintf('BULK-%03d', $i),
                'status' => 'active',
            ]);
            $expectedIds[] = $tree->id;
        }

        // Pull with limit=60 across multiple pages
        $collectedIds = [];
        $cursor = null;
        $pages = 0;
        $snapshotTime = null;

        do {
            $payload = [
                'since' => $sinceTime,
                'entities' => ['trees'],
                'limit' => 60,
            ];
            if ($cursor !== null) {
                $payload['cursor'] = $cursor;
            }

            $resp = $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/sync/pull', $payload)
                ->assertStatus(200);

            $data = $resp->json('data');
            $snapshotTime ??= $data['snapshot_time'];
            foreach ($data['changes']['trees'] as $row) {
                $collectedIds[] = $row['id'];
            }

            $hasMore = (bool) $data['has_more'];
            $cursor = $data['next_cursor'];
            $pages++;
        } while ($hasMore && $pages < 20);

        Carbon::setTestNow();

        $this->assertSame(4, $pages); // 60 + 60 + 60 + 25 = 205
        $this->assertCount(205, $collectedIds);
        $this->assertCount(205, array_unique($collectedIds));
        sort($expectedIds);
        sort($collectedIds);
        $this->assertSame($expectedIds, $collectedIds);
        $this->assertNotNull($snapshotTime);
    }

    public function test_vuln03_delta_sync_handles_concurrent_updates_tombstones_and_client_timezone(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $token = $superAdmin->createToken('test')->plainTextToken;
        $territory = $this->createTerritory('TOMB');

        $t0 = Carbon::parse('2026-11-24 12:00:00', 'UTC');
        Carbon::setTestNow($t0);

        $tree1 = Tree::create(['garden_id' => $territory['garden']->id, 'tree_code' => 'T-01', 'status' => 'active']);
        $tree2 = Tree::create(['garden_id' => $territory['garden']->id, 'tree_code' => 'T-02', 'status' => 'active']);
        $treeToDelete = Tree::create(['garden_id' => $territory['garden']->id, 'tree_code' => 'T-DEL', 'status' => 'active']);

        // Soft delete $treeToDelete at t0 + 10s
        Carbon::setTestNow($t0->copy()->addSeconds(10));
        $treeToDelete->delete();

        // Client passes `since` in +07:00 timezone (equivalent to t0 - 10s UTC)
        $sinceWib = $t0->copy()->subSeconds(10)->setTimezone('Asia/Jakarta')->toIso8601String();

        // Fetch Page 1 with limit=1 at t0 + 10s (pins snapshot_time = t0 + 10s)
        $page1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/sync/pull', [
                'since' => $sinceWib,
                'entities' => ['trees'],
                'limit' => 1,
            ])
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($page1['has_more']);
        $this->assertContains($treeToDelete->id, $page1['deleted_ids']['trees']);

        // Concurrent write occurs AFTER snapshot_time (at t0 + 30s) while pagination is still in progress!
        Carbon::setTestNow($t0->copy()->addSeconds(30));
        $concurrentTree = Tree::create([
            'garden_id' => $territory['garden']->id,
            'tree_code' => 'T-CONCURRENT',
            'status' => 'active',
        ]);

        // Fetch Page 2 using page1 cursor -> should NOT include concurrentTree yet
        $page2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/sync/pull', [
                'since' => $sinceWib,
                'entities' => ['trees'],
                'limit' => 1,
                'cursor' => $page1['next_cursor'],
            ])
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($page2['has_more']);
        $firstCycleIds = array_merge(
            array_column($page1['changes']['trees'], 'id'),
            array_column($page2['changes']['trees'], 'id')
        );
        $this->assertContains($tree1->id, $firstCycleIds);
        $this->assertContains($tree2->id, $firstCycleIds);
        $this->assertNotContains($concurrentTree->id, $firstCycleIds);

        // Next sync cycle starting from snapshot_time picks up the concurrentTree cleanly!
        $nextCycle = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/sync/pull', [
                'since' => $page1['snapshot_time'],
                'entities' => ['trees'],
                'limit' => 10,
            ])
            ->assertStatus(200)
            ->json('data');

        Carbon::setTestNow();

        $nextCycleIds = array_column($nextCycle['changes']['trees'], 'id');
        $this->assertSame([$concurrentTree->id], $nextCycleIds);
    }

    // =========================================================================
    // VULN-04: Prune Dependency Safety & Dry-Run
    // =========================================================================

    public function test_vuln04_prune_protects_trees_with_any_agricultural_activity_and_supports_dry_run(): void
    {
        $territory = $this->createTerritory('PRUNE');
        $garden = $territory['garden'];
        $user = $this->createUserWithRole('super_admin');
        $oldDeletedAt = Carbon::now()->subDays(45);

        $makeOldDeletedTree = function (string $code) use ($garden, $oldDeletedAt): Tree {
            $tree = Tree::create([
                'garden_id' => $garden->id,
                'tree_code' => $code,
                'status' => 'active',
            ]);
            $tree->delete();
            $tree->deleted_at = $oldDeletedAt;
            $tree->save();

            return $tree;
        };

        // Create 7 soft-deleted trees, each with ONE distinct child dependency type
        $treeWithMeasurement = $makeOldDeletedTree('PR-MEAS');
        TreeMeasurement::create([
            'tree_id' => $treeWithMeasurement->id,
            'measured_at' => now(),
            'diameter_cm' => 15,
            'recorded_by' => $user->id,
        ]);

        $treeWithFert = $makeOldDeletedTree('PR-FERT');
        $fert = FertilizationRecord::create([
            'tree_id' => $treeWithFert->id,
            'activity_date' => now(),
            'method' => 'tabur',
            'recorded_by' => $user->id,
        ]);

        $treeWithSpray = $makeOldDeletedTree('PR-SPRAY');
        $spray = SprayingRecord::create([
            'tree_id' => $treeWithSpray->id,
            'activity_date' => now(),
            'recorded_by' => $user->id,
        ]);

        $treeWithFlower = $makeOldDeletedTree('PR-FLOW');
        $flower = FloweringRecord::create([
            'tree_id' => $treeWithFlower->id,
            'flowering_date' => now()->toDateString(),
            'flowering_status' => 'mulai',
            'recorded_by' => $user->id,
        ]);

        $treeWithFruit = $makeOldDeletedTree('PR-FRUIT');
        $fruit = FruitDevelopmentRecord::create([
            'tree_id' => $treeWithFruit->id,
            'observation_date' => now()->toDateString(),
            'development_stage' => 'pentil',
            'recorded_by' => $user->id,
        ]);

        $treeWithPhoto = $makeOldDeletedTree('PR-PHOTO');
        ActivityPhoto::create([
            'activity_type' => Tree::PHOTO_ACTIVITY_TYPE,
            'activity_id' => $treeWithPhoto->id,
            'file_path' => 'photos/tree.jpg',
            'file_name' => 'tree.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 500,
        ]);

        $treeWithHarvest = $makeOldDeletedTree('PR-HARV');
        $season = HarvestSeason::create([
            'garden_id' => $garden->id,
            'name' => 'Musim 2026',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $session = HarvestSession::create([
            'season_id' => $season->id,
            'garden_id' => $garden->id,
            'harvest_date' => now()->toDateString(),
            'recorded_by' => $user->id,
        ]);
        HarvestRecord::create([
            'session_id' => $session->id,
            'garden_id' => $garden->id,
            'tree_id' => $treeWithHarvest->id,
            'fruit_count' => 5,
            'total_weight_kg' => 12.5,
        ]);

        // Plus 1 clean soft-deleted tree with ZERO dependencies (should be eligible)
        $cleanTree = $makeOldDeletedTree('PR-CLEAN');

        // Plus a soft-deleted garden with a MonitoringArea (must be skipped)
        $gardenWithMonitoring = Garden::create([
            'block_id' => $territory['block']->id,
            'name' => 'Kebun Monitoring',
            'ownership_type' => 'individual',
            'status' => 'active',
        ]);
        MonitoringArea::create([
            'garden_id' => $gardenWithMonitoring->id,
            'name' => 'Zona Sensor 1',
        ]);
        $gardenWithMonitoring->delete();
        $gardenWithMonitoring->deleted_at = $oldDeletedAt;
        $gardenWithMonitoring->save();

        // 1. Run --dry-run first: NOTHING should be deleted and NO AuditLog created
        $dryExit = Artisan::call('durian:prune-soft-deleted', ['--days' => 30, '--dry-run' => true]);
        $this->assertSame(0, $dryExit);
        $dryOutput = Artisan::output();
        $this->assertStringContainsString('[DRY RUN]', $dryOutput);
        $this->assertStringContainsString('1 would prune, 7 would skip', $dryOutput);
        $this->assertDatabaseHas('trees', ['id' => $cleanTree->id]);
        $this->assertSame(0, AuditLog::where('action', 'pruned')->count());

        // 2. Run actual prune
        $realExit = Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);
        $this->assertSame(0, $realExit);

        // Clean tree WAS pruned and audited
        $this->assertDatabaseMissing('trees', ['id' => $cleanTree->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pruned',
            'table_name' => 'trees',
            'record_id' => $cleanTree->id,
        ]);

        // All 7 trees with dependent records MUST still exist (no cascade deletion!)
        foreach ([
            $treeWithMeasurement,
            $treeWithFert,
            $treeWithSpray,
            $treeWithFlower,
            $treeWithFruit,
            $treeWithPhoto,
            $treeWithHarvest,
        ] as $protectedTree) {
            $this->assertSoftDeleted('trees', ['id' => $protectedTree->id]);
        }

        // All child activity records MUST remain intact
        $this->assertDatabaseHas('fertilization_records', ['id' => $fert->id]);
        $this->assertDatabaseHas('spraying_records', ['id' => $spray->id]);
        $this->assertDatabaseHas('flowering_records', ['id' => $flower->id]);
        $this->assertDatabaseHas('fruit_development_records', ['id' => $fruit->id]);

        // Garden with monitoring area MUST be preserved
        $this->assertSoftDeleted('gardens', ['id' => $gardenWithMonitoring->id]);
    }

    // =========================================================================
    // VULN-06: IoT Telemetry Rate Limiting
    // =========================================================================

    public function test_vuln06_iot_endpoint_throttles_bruteforce_and_per_device_flooding(): void
    {
        Config::set('durian.iot.ingest_key', 'valid-secret-iot-key');
        Config::set('durian.iot.auth_failure_limit_per_minute', 3);
        Config::set('durian.iot.ingest_rate_limit_per_minute', 3);
        Config::set('durian.iot.ingest_ip_rate_limit_per_minute', 10);

        $territory = $this->createTerritory('IOT');
        $area = MonitoringArea::create([
            'garden_id' => $territory['garden']->id,
            'name' => 'Area IoT',
        ]);
        $device1 = IotDevice::create([
            'monitoring_area_id' => $area->id,
            'device_code' => 'DEV-001',
            'device_name' => 'Sensor 1',
            'device_type' => 'soil',
            'status' => 'active',
        ]);
        $device2 = IotDevice::create([
            'monitoring_area_id' => $area->id,
            'device_code' => 'DEV-002',
            'device_name' => 'Sensor 2',
            'device_type' => 'soil',
            'status' => 'active',
        ]);

        // Clear any leftover limiter keys
        RateLimiter::clear('iot-auth-fail:127.0.0.1');
        RateLimiter::clear('iot-device:dev-001');
        RateLimiter::clear('iot-device:dev-002');
        RateLimiter::clear('iot-ip:127.0.0.1');

        // 1. Brute-force attempts with rotating headers/device codes still hit IP auth limit -> 429
        for ($i = 1; $i <= 3; $i++) {
            $this->withHeader('X-IoT-Ingest-Key', "wrong-key-{$i}")
                ->postJson('/api/v1/iot/telemetry', ['device_code' => "FAKE-{$i}"])
                ->assertStatus(401);
        }
        $this->withHeader('X-IoT-Ingest-Key', 'wrong-key-4')
            ->postJson('/api/v1/iot/telemetry', ['device_code' => 'FAKE-4'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        // 2. Legitimate device 1 burst within limit -> 201, 4th request -> 429
        for ($i = 1; $i <= 3; $i++) {
            $this->withHeader('X-IoT-Ingest-Key', 'valid-secret-iot-key')
                ->postJson('/api/v1/iot/telemetry', [
                    'device_code' => $device1->device_code,
                    'temperature_c' => 28.5,
                ])
                ->assertStatus(201);
        }
        $this->withHeader('X-IoT-Ingest-Key', 'valid-secret-iot-key')
            ->postJson('/api/v1/iot/telemetry', [
                'device_code' => $device1->device_code,
                'temperature_c' => 28.5,
            ])
            ->assertStatus(429);

        // 3. Legitimate device 2 from same IP is NOT blocked by device 1's per-device limit
        $this->withHeader('X-IoT-Ingest-Key', 'valid-secret-iot-key')
            ->postJson('/api/v1/iot/telemetry', [
                'device_code' => $device2->device_code,
                'temperature_c' => 29.0,
            ])
            ->assertStatus(201);
    }

    // =========================================================================
    // VULN-08: Territorial Scoping Verification for Dropdowns & Server-Side Checks
    // =========================================================================

    public function test_vuln08_territorial_scope_isolates_villages_blocks_and_gardens(): void
    {
        $territoryA = $this->createTerritory('ALPHA');
        $territoryB = $this->createTerritory('BETA');

        $pemdesA = $this->createUserWithRole('pemerintah_desa', [
            'village_id' => $territoryA['village']->id,
        ]);
        $superAdmin = $this->createUserWithRole('super_admin');

        $scope = app(TerritoryScopeService::class);

        // Pemerintah Desa A can ONLY see Village A, Block A, Garden A
        $this->assertSame([$territoryA['village']->id], $scope->getAccessibleVillageIds($pemdesA));
        $this->assertSame([$territoryA['block']->id], $scope->getAccessibleBlockIds($pemdesA));
        $this->assertSame([$territoryA['garden']->id], $scope->getAccessibleGardenIds($pemdesA));

        $this->assertTrue($scope->canAccessVillage($pemdesA, $territoryA['village']->id));
        $this->assertFalse($scope->canAccessVillage($pemdesA, $territoryB['village']->id));
        $this->assertTrue($scope->canAccessBlock($pemdesA, $territoryA['block']->id));
        $this->assertFalse($scope->canAccessBlock($pemdesA, $territoryB['block']->id));
        $this->assertTrue($scope->canAccessGarden($pemdesA, $territoryA['garden']->id));
        $this->assertFalse($scope->canAccessGarden($pemdesA, $territoryB['garden']->id));

        // Super admin remains unrestricted
        $this->assertNull($scope->getAccessibleVillageIds($superAdmin));
        $this->assertNull($scope->getAccessibleBlockIds($superAdmin));
        $this->assertNull($scope->getAccessibleGardenIds($superAdmin));
    }
}
