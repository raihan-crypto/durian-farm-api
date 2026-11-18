<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DurianVarietyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

        $this->adminUser = User::create([
            'name' => 'Admin Varietas',
            'email' => 'admin.variety@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    public function test_admin_can_crud_durian_varieties_with_catalog_fields(): void
    {
        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/durian-varieties', [
                'code' => 'MK-01',
                'name' => 'Musang King',
                'origin' => 'Malaysia',
                'description' => 'Durian premium berdaging kuning pekat.',
                'characteristics' => 'Rasa manis legit sedikit pahit, biji kecil.',
                'is_active' => true,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'MK-01')
            ->assertJsonPath('data.name', 'Musang King')
            ->assertJsonPath('data.characteristics', 'Rasa manis legit sedikit pahit, biji kecil.')
            ->assertJsonPath('data.is_active', true);

        $varietyId = $createResp->json('data.id');

        // Duplicate name or code rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/durian-varieties', [
                'code' => 'MK-01',
                'name' => 'Musang King',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);

        DurianVariety::create([
            'code' => 'BW-01',
            'name' => 'Bawor',
            'origin' => 'Banyumas',
            'is_active' => false,
        ]);

        // Filter active varieties and search
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/durian-varieties?is_active=true&search=Musang')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $varietyId);

        // Update variety
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/durian-varieties/{$varietyId}", [
                'code' => 'MK-01',
                'name' => 'Musang King D197',
                'origin' => 'Kelantan, Malaysia',
                'is_active' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Musang King D197');

        // Prevent deleting variety referenced by a tree
        $village = Village::create(['name' => 'Desa Varietas']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok V1']);
        $garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun V1']);
        Tree::create([
            'garden_id' => $garden->id,
            'variety_id' => $varietyId,
            'tree_code' => 'MK-001',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/durian-varieties/{$varietyId}")
            ->assertStatus(422);

        // Unused variety can be deleted
        $unused = DurianVariety::where('code', 'BW-01')->firstOrFail();
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/durian-varieties/{$unused->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('durian_varieties', ['id' => $unused->id]);
    }

    public function test_admin_can_upload_and_delete_variety_photo(): void
    {
        Storage::fake('public');

        $variety = DurianVariety::create([
            'code' => 'DH-01',
            'name' => 'Duri Hitam',
            'is_active' => true,
        ]);

        $uploadResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post("/api/v1/durian-varieties/{$variety->id}/photo", [
                'photo' => UploadedFile::fake()->image('duri-hitam.jpg', 600, 600),
            ], ['Accept' => 'application/json']);

        $uploadResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $variety->refresh();
        $this->assertNotNull($variety->photo_path);
        Storage::disk('public')->assertExists($variety->photo_path);

        $oldPath = $variety->photo_path;

        // Delete photo
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/durian-varieties/{$variety->id}/photo")
            ->assertStatus(200)
            ->assertJsonPath('data.photo_url', null);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_farmer_can_view_varieties_but_cannot_manage_them(): void
    {
        DurianVariety::create(['code' => 'MT-01', 'name' => 'Montong', 'is_active' => true]);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Lihat Varietas',
            'email' => 'petani.var@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/durian-varieties')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/durian-varieties', ['name' => 'Varietas Ilegal'])
            ->assertStatus(403);
    }
}
