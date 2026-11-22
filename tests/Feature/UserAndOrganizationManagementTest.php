<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAndOrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->first();

        $this->adminUser = User::create([
            'name' => 'Admin Utama',
            'email' => 'admin.test@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);

        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    public function test_can_list_and_create_organization(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/organizations', [
                'name' => 'Koperasi Tani Durian Makmur',
                'description' => 'Koperasi petani durian wilayah Bogor',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Koperasi Tani Durian Makmur',
                ],
            ]);

        $orgId = $response->json('data.id');

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/organizations?search=Koperasi');

        $listResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment(['name' => 'Koperasi Tani Durian Makmur']);
    }

    public function test_can_update_and_delete_organization(): void
    {
        $org = Organization::create([
            'name' => 'Org Lama',
            'description' => 'Desc lama',
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/organizations/{$org->id}", [
                'name' => 'Org Diperbarui',
                'description' => 'Desc baru',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Org Diperbarui');

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/organizations/{$org->id}");

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('organizations', ['id' => $org->id]);
    }

    public function test_can_create_user_with_assigned_role(): void
    {
        $org = Organization::create(['name' => 'Perkebunan Indah']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/users', [
                'organization_id' => $org->id,
                'name' => 'Ahmad Subardjo',
                'email' => 'ahmad@durianfarm.test',
                'password' => 'durian12345',
                'phone' => '082199887766',
                'is_active' => true,
                'role_names' => ['farmer'],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pengguna berhasil dibuat',
                'data' => [
                    'name' => 'Ahmad Subardjo',
                    'email' => 'ahmad@durianfarm.test',
                    'phone' => '082199887766',
                ],
            ]);

        $createdUser = User::where('email', 'ahmad@durianfarm.test')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole('farmer'));
    }

    public function test_can_update_and_deactivate_user(): void
    {
        $user = User::create([
            'name' => 'Bambang',
            'email' => 'bambang@durianfarm.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'Bambang Sudirman',
                'phone' => '0855667788',
                'role_names' => ['plantation_manager'],
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Bambang Sudirman');

        $this->assertTrue($user->fresh()->hasRole('plantation_manager'));

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/users/{$user->id}");

        $deleteResponse->assertStatus(200);
        $this->assertFalse((bool) $user->fresh()->is_active);
    }
}
