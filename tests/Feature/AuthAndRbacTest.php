<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_user_can_login_with_valid_credentials_and_receive_token(): void
    {
        $user = User::create([
            'name' => 'Petani Joko',
            'email' => 'joko@durianfarm.test',
            'password' => Hash::make('password123'),
            'phone' => '08123456789',
            'is_active' => true,
        ]);
        $farmerRole = Role::where('name', 'farmer')->first();
        $user->roles()->attach($farmerRole->id);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'joko@durianfarm.test',
            'password' => 'password123',
            'device_name' => 'flutter-mobile-app',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email'],
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::create([
            'name' => 'Petani Joko',
            'email' => 'joko@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'joko@durianfarm.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
            ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Petani Nonaktif',
            'email' => 'inactive@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@durianfarm.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
            ]);
    }

    public function test_authenticated_user_can_get_profile_via_me_endpoint(): void
    {
        $org = Organization::create(['name' => 'Kebun Sejahtera']);
        $user = User::create([
            'organization_id' => $org->id,
            'name' => 'Mandor Budi',
            'email' => 'budi@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $managerRole = Role::where('name', 'plantation_manager')->first();
        $user->roles()->attach($managerRole->id);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Mandor Budi',
                        'email' => 'budi@durianfarm.test',
                    ],
                ],
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'Petani Joko',
            'email' => 'joko@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout berhasil',
            ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    public function test_role_and_permission_middleware_blocks_unauthorized_users(): void
    {
        $farmer = User::create([
            'name' => 'Petani Joko',
            'email' => 'joko@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerRole = Role::where('name', 'farmer')->first();
        $farmer->roles()->attach($farmerRole->id);

        $token = $farmer->createToken('test')->plainTextToken;

        // Farmer does NOT have permission 'roles.view'
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/roles');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki izin yang dibutuhkan untuk tindakan ini',
            ]);
    }

    public function test_super_admin_bypasses_all_permission_restrictions(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Boss',
            'email' => 'boss@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $superAdmin->roles()->attach($superAdminRole->id);

        $token = $superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
