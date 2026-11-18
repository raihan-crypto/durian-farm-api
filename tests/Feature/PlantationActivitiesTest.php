<?php

namespace Tests\Feature;

use App\Models\AgriculturalProduct;
use App\Models\Block;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlantationActivitiesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected Tree $treeA;

    protected Tree $treeB;

    protected AgriculturalProduct $npkProduct;

    protected AgriculturalProduct $organicProduct;

    protected AgriculturalProduct $fungicideProduct;

    protected AgriculturalProduct $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->adminUser = User::create([
            'name' => 'Admin Aktivitas',
            'email' => 'admin.activities@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Aktivitas']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok A']);
        $this->gardenA = Garden::create(['block_id' => $block->id, 'name' => 'Kebun A']);
        $this->gardenB = Garden::create(['block_id' => $block->id, 'name' => 'Kebun B']);

        $this->treeA = Tree::create(['garden_id' => $this->gardenA->id, 'tree_code' => 'TR-A1', 'status' => 'active']);
        $this->treeB = Tree::create(['garden_id' => $this->gardenB->id, 'tree_code' => 'TR-B1', 'status' => 'active']);

        $catPupuk = ProductCategory::create(['name' => 'Pupuk', 'is_active' => true]);
        $catPestisida = ProductCategory::create(['name' => 'Fungisida', 'is_active' => true]);

        $this->npkProduct = AgriculturalProduct::create([
            'category_id' => $catPupuk->id,
            'name' => 'NPK 16-16-16',
            'brand' => 'Mutiara',
            'unit' => 'gram',
            'is_active' => true,
        ]);
        $this->organicProduct = AgriculturalProduct::create([
            'category_id' => $catPupuk->id,
            'name' => 'Asam Humat',
            'unit' => 'gram',
            'is_active' => true,
        ]);
        $this->fungicideProduct = AgriculturalProduct::create([
            'category_id' => $catPestisida->id,
            'name' => 'Mankozeb 80 WP',
            'unit' => 'gram',
            'is_active' => true,
        ]);
        $this->inactiveProduct = AgriculturalProduct::create([
            'category_id' => $catPupuk->id,
            'name' => 'Pupuk Lama Nonaktif',
            'unit' => 'kg',
            'is_active' => false,
        ]);
    }

    public function test_can_record_multi_product_fertilization_and_reject_inactive_or_empty_items(): void
    {
        Storage::fake('public');

        // Reject when items are missing
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/fertilization-records', [
                'tree_id' => $this->treeA->id,
                'method' => 'tabur',
                'items' => [],
            ])
            ->assertStatus(422);

        // Reject inactive product
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/fertilization-records', [
                'tree_id' => $this->treeA->id,
                'method' => 'tabur',
                'items' => [
                    ['product_id' => $this->inactiveProduct->id, 'quantity' => 2],
                ],
            ])
            ->assertStatus(422);

        // Valid multi-product fertilization with photo and automatic unit fallback
        $resp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post('/api/v1/fertilization-records', [
                'tree_id' => $this->treeA->id,
                'activity_date' => '2026-03-10 08:00:00',
                'method' => 'kocor',
                'dosage_notes' => 'Dilarutkan dalam 20 liter air',
                'notes' => 'Pemupukan vegetatif',
                'items' => [
                    ['product_id' => $this->npkProduct->id, 'quantity' => 250],
                    ['product_id' => $this->organicProduct->id, 'quantity' => 50, 'unit' => 'gram', 'notes' => 'Pembenah tanah'],
                ],
                'photos' => [UploadedFile::fake()->image('pupuk.jpg', 400, 400)],
            ], ['Accept' => 'application/json']);

        $resp->assertStatus(201)
            ->assertJsonPath('data.method', 'kocor')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.unit', 'gram')
            ->assertJsonPath('data.items.0.category_name', 'Pupuk')
            ->assertJsonCount(1, 'data.photos');

        // Filter by product_id & tree_id
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/fertilization-records?tree_id={$this->treeA->id}&product_id={$this->npkProduct->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/fertilization-records/methods')
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_can_record_multi_product_spraying_with_photos(): void
    {
        Storage::fake('public');

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post('/api/v1/spraying-records', [
                'tree_id' => $this->treeA->id,
                'activity_date' => '2026-03-15 06:30:00',
                'spraying_method' => 'knapsack sprayer',
                'dosage_notes' => 'Semprot merata bawah daun',
                'items' => [
                    ['product_id' => $this->fungicideProduct->id, 'quantity' => 40, 'unit' => 'gram'],
                    ['product_id' => $this->organicProduct->id, 'quantity' => 20],
                ],
                'photos' => [UploadedFile::fake()->image('semprot.png', 400, 400)],
            ], ['Accept' => 'application/json']);

        $resp->assertStatus(201)
            ->assertJsonPath('data.spraying_method', 'knapsack sprayer')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.photos');

        $recordId = $resp->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/spraying-records/{$recordId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $recordId);
    }

    public function test_can_record_historical_flowering_and_fruit_development_without_overwriting(): void
    {
        // Two flowering observations on the same tree
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/flowering-records', [
                'tree_id' => $this->treeA->id,
                'flowering_date' => '2026-04-01',
                'flowering_status' => 'mulai',
                'flower_count' => 40,
                'notes' => 'Muncul mata ketam',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.flowering_status_label', 'Mulai Berbunga');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/flowering-records', [
                'tree_id' => $this->treeA->id,
                'flowering_date' => '2026-04-15',
                'flowering_status' => 'berbunga',
                'flower_count' => 110,
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/flowering-records?tree_id={$this->treeA->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.flowering_status', 'berbunga')
            ->assertJsonPath('data.items.1.flowering_status', 'mulai');

        // Two fruit development observations on the same tree
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/fruit-development-records', [
                'tree_id' => $this->treeA->id,
                'observation_date' => '2026-05-05',
                'fruit_count' => 45,
                'development_stage' => 'pentil',
                'notes' => 'Bakal buah sehat',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.development_stage_label', 'Pembentukan Bakal Buah / Pentil');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/fruit-development-records', [
                'tree_id' => $this->treeA->id,
                'observation_date' => '2026-06-05',
                'fruit_count' => 28,
                'development_stage' => 'pembesaran',
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/fruit-development-records?tree_id={$this->treeA->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.development_stage', 'pembesaran')
            ->assertJsonPath('data.items.1.development_stage', 'pentil');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/flowering-records/statuses')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/fruit-development-records/stages')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_farmer_activities_are_strictly_scoped_to_assigned_gardens(): void
    {
        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Kebun A',
            'email' => 'petani.act@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'name' => 'Petani Kebun A']);
        GardenFarmer::create([
            'garden_id' => $this->gardenA->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        // Farmer CAN record on assigned treeA
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/fertilization-records', [
                'tree_id' => $this->treeA->id,
                'method' => 'tabur',
                'items' => [['product_id' => $this->npkProduct->id, 'quantity' => 100]],
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/flowering-records', [
                'tree_id' => $this->treeA->id,
                'flowering_status' => 'mulai',
            ])
            ->assertStatus(201);

        // Farmer CANNOT record on unassigned treeB
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/fertilization-records', [
                'tree_id' => $this->treeB->id,
                'method' => 'tabur',
                'items' => [['product_id' => $this->npkProduct->id, 'quantity' => 100]],
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/spraying-records', [
                'tree_id' => $this->treeB->id,
                'items' => [['product_id' => $this->fungicideProduct->id, 'quantity' => 25]],
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/flowering-records', [
                'tree_id' => $this->treeB->id,
                'flowering_status' => 'mulai',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/fruit-development-records', [
                'tree_id' => $this->treeB->id,
                'development_stage' => 'pentil',
            ])
            ->assertStatus(403);
    }
}
