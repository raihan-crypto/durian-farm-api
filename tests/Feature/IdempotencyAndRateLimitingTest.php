<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Garden;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyAndRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Garden $garden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->adminUser = User::create([
            'name' => 'Admin Idempotent',
            'email' => 'admin.idempotent@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Idempotent']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok Idempotent']);
        $this->garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Idempotent']);
    }

    public function test_sync_push_with_idempotency_key_prevents_duplicate_replays(): void
    {
        $idempotencyKey = (string) Str::uuid();
        $txId = (string) Str::uuid();

        $payload = [
            'device_identifier' => 'device-test-001',
            'operations' => [
                [
                    'client_transaction_id' => $txId,
                    'entity_type' => 'tree_measurement',
                    'entity_id' => (string) Str::uuid(),
                    'payload' => [
                        'tree_id' => (string) Str::uuid(),
                        'measured_at' => now()->toISOString(),
                        'diameter_cm' => 22.4,
                    ],
                ],
            ],
        ];

        // First attempt
        $firstResp = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/sync/push', $payload);

        $firstResp->assertStatus(200);
        $this->assertFalse($firstResp->headers->has('X-Idempotent-Replayed'));

        // Second attempt with exact same key must return replayed response
        $secondResp = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/sync/push', $payload);

        $secondResp->assertStatus(200);
        $this->assertTrue($secondResp->headers->has('X-Idempotent-Replayed'));
        $this->assertEquals($firstResp->json(), $secondResp->json());
    }

    public function test_auth_login_has_rate_limiting(): void
    {
        // Hit login multiple times with invalid credentials
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'attacker@durianfarm.test',
                'password' => 'wrongpass',
            ]);
        }

        // 11th request must be throttled with HTTP 429
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'attacker@durianfarm.test',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(429);
    }
}
