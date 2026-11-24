<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SanctumTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $org = Organization::create(['name' => 'Organisasi Perkebunan']);
        $this->user = User::create([
            'organization_id' => $org->id,
            'name' => 'Petani Token Test',
            'email' => 'token.test@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach(Role::where('name', 'petani')->firstOrFail());
    }

    public function test_login_returns_token_with_expiration_timestamp(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'token.test@durianfarm.test',
            'password' => 'password123',
            'device_name' => 'mobile-field-unit',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                    'user',
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_valid_token_allows_authenticated_api_access(): void
    {
        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'token.test@durianfarm.test');
    }

    public function test_expired_token_is_rejected_with_401(): void
    {
        // Issue token created 31 days ago (past the 30-day expiration window)
        $tokenInstance = $this->user->createToken('old-device');
        $plainToken = $tokenInstance->plainTextToken;

        // Manipulate created_at to simulate 31 days in the past
        PersonalAccessToken::where('id', $tokenInstance->accessToken->id)->update([
            'created_at' => Carbon::now()->subDays(31),
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_token_within_expiration_window_is_accepted(): void
    {
        // Issue token created 20 days ago (within 30-day window)
        $tokenInstance = $this->user->createToken('active-device');
        $plainToken = $tokenInstance->plainTextToken;

        PersonalAccessToken::where('id', $tokenInstance->accessToken->id)->update([
            'created_at' => Carbon::now()->subDays(20),
            'expires_at' => Carbon::now()->addDays(10),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'token.test@durianfarm.test');
    }

    public function test_logout_revokes_token_immediately(): void
    {
        $token = $this->user->createToken('logout-device')->plainTextToken;

        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200);

        // Token record is permanently deleted from database
        $this->assertCount(0, $this->user->fresh()->tokens);

        // Clear in-memory auth guard memoization in test runner
        $this->app['auth']->forgetGuards();

        // Immediate subsequent request must fail with 401
        $subsequentResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $subsequentResponse->assertStatus(401);
    }
}
