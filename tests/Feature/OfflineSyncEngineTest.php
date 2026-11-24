<?php

namespace Tests\Feature;

use App\Models\AgriculturalProduct;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\FertilizationRecord;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use App\Services\TreeQrCodeService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $farmerUser;

    protected string $farmerToken;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected Tree $treeA;

    protected Tree $treeB;

    protected AgriculturalProduct $product;

    protected HarvestSeason $seasonA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $villageA = Village::create(['name' => 'Desa Sync A']);
        $villageB = Village::create(['name' => 'Desa Sync B']);
        $blockA = Block::create(['village_id' => $villageA->id, 'name' => 'Blok A']);
        $blockB = Block::create(['village_id' => $villageB->id, 'name' => 'Blok B']);
        $this->gardenA = Garden::create(['block_id' => $blockA->id, 'name' => 'Kebun Sync A']);
        $this->gardenB = Garden::create(['block_id' => $blockB->id, 'name' => 'Kebun Sync B']);

        $activeVariety = DurianVariety::create(['code' => 'MK', 'name' => 'Musang King', 'is_active' => true]);
        DurianVariety::create(['code' => 'OLD', 'name' => 'Varietas Nonaktif', 'is_active' => false]);

        $this->treeA = Tree::create([
            'garden_id' => $this->gardenA->id,
            'variety_id' => $activeVariety->id,
            'tree_code' => 'SYNC-A1',
            'status' => 'active',
        ]);
        app(TreeQrCodeService::class)->ensureForTree($this->treeA);

        $this->treeB = Tree::create([
            'garden_id' => $this->gardenB->id,
            'variety_id' => $activeVariety->id,
            'tree_code' => 'SYNC-B1',
            'status' => 'active',
        ]);

        $cat = ProductCategory::create(['name' => 'Pupuk', 'is_active' => true]);
        $this->product = AgriculturalProduct::create([
            'category_id' => $cat->id,
            'name' => 'NPK Mutiara',
            'unit' => 'gram',
            'is_active' => true,
        ]);

        $this->seasonA = HarvestSeason::create([
            'garden_id' => $this->gardenA->id,
            'name' => 'Musim Sync 2026',
            'start_date' => '2026-06-01',
            'status' => 'active',
        ]);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $this->farmerUser = User::create([
            'name' => 'Petani Offline',
            'email' => 'petani.sync@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->farmerUser->roles()->attach($farmerRole->id);
        $this->farmerToken = $this->farmerUser->createToken('flutter-mobile')->plainTextToken;

        $farmer = Farmer::create(['user_id' => $this->farmerUser->id, 'name' => 'Petani Offline']);
        GardenFarmer::create([
            'garden_id' => $this->gardenA->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);
    }

    public function test_farmer_bootstrap_returns_only_assigned_territory_and_active_masters(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->getJson('/api/v1/sync/bootstrap');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.villages')
            ->assertJsonCount(1, 'data.blocks')
            ->assertJsonCount(1, 'data.gardens')
            ->assertJsonCount(1, 'data.trees')
            ->assertJsonCount(1, 'data.durian_varieties')
            ->assertJsonCount(1, 'data.agricultural_products')
            ->assertJsonCount(1, 'data.harvest_seasons')
            ->assertJsonPath('data.trees.0.id', $this->treeA->id);

        $this->assertNotNull($response->json('data.trees.0.qr_token'));
        $this->assertNotNull($response->json('data.server_time'));
    }

    public function test_push_preserves_client_uuids_is_idempotent_and_supports_deferred_photo_upload(): void
    {
        Storage::fake('public');

        $measurementId = (string) Str::uuid();
        $fertilizationId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $harvestRecordId = (string) Str::uuid();

        $payload = [
            'device_identifier' => 'android-field-01',
            'operations' => [
                [
                    'client_transaction_id' => 'tx-meas-001',
                    'entity_type' => 'tree_measurement',
                    'entity_id' => $measurementId,
                    'payload' => [
                        'tree_id' => $this->treeA->id,
                        'measured_at' => '2026-07-01 08:00:00',
                        'diameter_cm' => 19.2,
                        'height_m' => 4.5,
                    ],
                ],
                [
                    'client_transaction_id' => 'tx-fert-001',
                    'entity_type' => 'fertilization',
                    'entity_id' => $fertilizationId,
                    'payload' => [
                        'tree_id' => $this->treeA->id,
                        'activity_date' => '2026-07-01 09:00:00',
                        'method' => 'tabur',
                        'items' => [
                            ['product_id' => $this->product->id, 'quantity' => 300],
                        ],
                    ],
                ],
                [
                    'client_transaction_id' => 'tx-sess-001',
                    'entity_type' => 'harvest_session',
                    'entity_id' => $sessionId,
                    'payload' => [
                        'season_id' => $this->seasonA->id,
                        'harvest_date' => '2026-07-02 07:30:00',
                        'session_name' => 'Panen Offline Pagi',
                    ],
                ],
                [
                    'client_transaction_id' => 'tx-harv-001',
                    'entity_type' => 'harvest_record',
                    'entity_id' => $harvestRecordId,
                    'payload' => [
                        'session_id' => $sessionId,
                        'tree_id' => $this->treeA->id,
                        'details' => [
                            ['fruit_grade' => 'Grade A', 'fruit_count' => 6, 'weight_kg' => 14.4],
                        ],
                    ],
                ],
            ],
        ];

        $firstPush = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/push', $payload);

        $firstPush->assertStatus(200)
            ->assertJsonPath('data.summary.total', 4)
            ->assertJsonPath('data.summary.synced', 4)
            ->assertJsonPath('data.summary.duplicates', 0)
            ->assertJsonPath('data.summary.conflicts', 0)
            ->assertJsonPath('data.summary.failed', 0);

        // Client-generated UUIDs are preserved verbatim
        $this->assertDatabaseHas('tree_measurements', ['id' => $measurementId]);
        $this->assertDatabaseHas('fertilization_records', ['id' => $fertilizationId]);
        $this->assertDatabaseHas('harvest_sessions', ['id' => $sessionId]);
        $this->assertDatabaseHas('harvest_records', ['id' => $harvestRecordId, 'fruit_count' => 6]);

        // Re-sending identical transactions is strictly idempotent (zero duplicates created)
        $retryPush = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/push', $payload);

        $retryPush->assertStatus(200)
            ->assertJsonPath('data.summary.synced', 0)
            ->assertJsonPath('data.summary.duplicates', 4)
            ->assertJsonPath('data.summary.failed', 0);

        $this->assertSame(1, TreeMeasurement::count());
        $this->assertSame(1, FertilizationRecord::count());
        $this->assertSame(1, HarvestSession::count());
        $this->assertSame(1, HarvestRecord::count());

        // Deferred photo upload links directly to client-generated fertilization UUID (SRS 12.7)
        $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->post('/api/v1/activity-photos', [
                'activity_type' => 'fertilization',
                'activity_id' => $fertilizationId,
                'photo' => UploadedFile::fake()->image('offline-photo.jpg'),
                'caption' => 'Diunggah setelah online',
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.activity_id', $fertilizationId);
    }

    public function test_push_detects_conflicts_and_blocks_unauthorized_territory_operations(): void
    {
        $existingFert = FertilizationRecord::create([
            'tree_id' => $this->treeA->id,
            'activity_date' => '2026-07-01 07:00:00',
            'method' => 'tabur',
            'notes' => 'Catatan asli tidak boleh tertimpa',
            'recorded_by' => $this->farmerUser->id,
        ]);

        $session = HarvestSession::create([
            'season_id' => $this->seasonA->id,
            'garden_id' => $this->gardenA->id,
            'harvest_date' => '2026-07-05 08:00:00',
            'session_name' => 'Sesi Konflik',
        ]);
        HarvestRecord::create([
            'session_id' => $session->id,
            'garden_id' => $this->gardenA->id,
            'tree_id' => $this->treeA->id,
            'fruit_count' => 5,
            'total_weight_kg' => 10.0,
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/push', [
                'device_identifier' => 'android-field-02',
                'operations' => [
                    // Conflict 1: same entity_id as existing historical record with new tx id
                    [
                        'client_transaction_id' => 'tx-conflict-fert',
                        'entity_type' => 'fertilization',
                        'entity_id' => $existingFert->id,
                        'payload' => [
                            'tree_id' => $this->treeA->id,
                            'method' => 'kocor',
                            'notes' => 'Mencoba menimpa',
                            'items' => [['product_id' => $this->product->id, 'quantity' => 50]],
                        ],
                    ],
                    // Conflict 2: different entity_id for same (session_id, tree_id)
                    [
                        'client_transaction_id' => 'tx-conflict-harvest',
                        'entity_type' => 'harvest_record',
                        'entity_id' => (string) Str::uuid(),
                        'payload' => [
                            'session_id' => $session->id,
                            'tree_id' => $this->treeA->id,
                            'fruit_count' => 9,
                            'total_weight_kg' => 18.0,
                        ],
                    ],
                    // Failure: tree in unassigned garden B
                    [
                        'client_transaction_id' => 'tx-unauth-garden',
                        'entity_type' => 'flowering',
                        'entity_id' => (string) Str::uuid(),
                        'payload' => [
                            'tree_id' => $this->treeB->id,
                            'flowering_status' => 'mulai',
                        ],
                    ],
                ],
            ]);

        $resp->assertStatus(200)
            ->assertJsonPath('data.summary.conflicts', 2)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.1.status', 'conflict')
            ->assertJsonPath('data.results.2.status', 'failed');

        // Historical record was NOT overwritten
        $this->assertSame('Catatan asli tidak boleh tertimpa', $existingFert->fresh()->notes);

        // Sync logs captured all 3 statuses
        $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->getJson('/api/v1/sync/logs?sync_status=conflict')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_pull_and_changes_return_only_incremental_delta_in_accessible_gardens(): void
    {
        $cursor = Carbon::now()->addSeconds(1)->toISOString();

        Carbon::setTestNow(Carbon::now()->addSeconds(5));

        $newTreeA = Tree::create([
            'garden_id' => $this->gardenA->id,
            'tree_code' => 'SYNC-A2',
            'status' => 'active',
        ]);
        Tree::create([
            'garden_id' => $this->gardenB->id,
            'tree_code' => 'SYNC-B2',
            'status' => 'active',
        ]);

        $pullResp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'since' => $cursor,
                'entities' => ['trees'],
            ]);

        Carbon::setTestNow();

        $pullResp->assertStatus(200)
            ->assertJsonCount(1, 'data.changes.trees')
            ->assertJsonPath('data.changes.trees.0.id', $newTreeA->id);

        $changesResp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->getJson('/api/v1/sync/changes?since='.urlencode($cursor).'&entities[]=trees');

        $changesResp->assertStatus(200)
            ->assertJsonCount(1, 'data.changes.trees');
    }

    public function test_delta_pull_includes_consistent_qr_token_for_trees(): void
    {
        $qr = app(TreeQrCodeService::class)->ensureForTree($this->treeA);
        $this->treeA->touch();

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'entities' => ['trees'],
            ]);

        $resp->assertStatus(200);
        $trees = $resp->json('data.changes.trees');
        $treeRecord = collect($trees)->firstWhere('id', $this->treeA->id);

        $this->assertNotNull($treeRecord);
        $this->assertSame($qr->public_token, $treeRecord['qr_token']);
    }

    public function test_pull_request_with_invalid_base64_cursor_returns_422(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'cursor' => '!!!not-valid-base64???',
            ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['cursor']);
    }

    public function test_pull_request_with_cursor_missing_snapshot_time_returns_422(): void
    {
        $badCursor = base64_encode(json_encode(['foo' => 'bar']));

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'cursor' => $badCursor,
            ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['cursor']);
    }

    public function test_pull_request_with_garbage_snapshot_time_returns_422(): void
    {
        $badCursor = base64_encode(json_encode(['snapshot_time' => 'bukan-format-tanggal']));

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'cursor' => $badCursor,
            ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['cursor']);
    }

    public function test_pull_request_with_absurd_future_year_returns_422(): void
    {
        $badCursor = base64_encode(json_encode(['snapshot_time' => '9999-12-31T23:59:59Z']));

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'cursor' => $badCursor,
            ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['cursor']);
    }

    public function test_pull_request_with_valid_cursor_returns_200_with_normal_pagination(): void
    {
        $validCursor = base64_encode(json_encode([
            'snapshot_time' => Carbon::now()->subMinutes(1)->toISOString(),
            'entities' => [],
            'tombstones' => [],
        ]));

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->farmerToken)
            ->postJson('/api/v1/sync/pull', [
                'cursor' => $validCursor,
            ]);

        $resp->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'server_time',
                    'changes',
                    'next_cursor',
                    'has_more',
                ],
            ]);
    }
}
