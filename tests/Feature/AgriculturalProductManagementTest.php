<?php

namespace Tests\Feature;

use App\Models\AgriculturalProduct;
use App\Models\Block;
use App\Models\FertilizationItem;
use App\Models\FertilizationRecord;
use App\Models\Garden;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgriculturalProductManagementTest extends TestCase
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
            'name' => 'Admin Produk',
            'email' => 'admin.products@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    public function test_admin_can_crud_product_categories_with_deactivation_and_delete_protection(): void
    {
        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/product-categories', [
                'name' => 'Pupuk Anorganik',
                'description' => 'Pupuk kimia majemuk dan tunggal',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Pupuk Anorganik')
            ->assertJsonPath('data.is_active', true);

        $categoryId = $createResp->json('data.id');

        // Duplicate name rejected
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/product-categories', [
                'name' => 'Pupuk Anorganik',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Deactivate category
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/product-categories/{$categoryId}", [
                'name' => 'Pupuk Anorganik',
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Filter by is_active
        ProductCategory::create(['name' => 'Fungisida', 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/product-categories?is_active=false')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $categoryId);

        // Cannot delete category that has products
        AgriculturalProduct::create([
            'category_id' => $categoryId,
            'name' => 'NPK Mutiara 16-16-16',
            'unit' => 'kg',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/product-categories/{$categoryId}")
            ->assertStatus(422);

        // Empty category can be deleted
        $emptyCategory = ProductCategory::where('name', 'Fungisida')->firstOrFail();
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/product-categories/{$emptyCategory->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('product_categories', ['id' => $emptyCategory->id]);
    }

    public function test_admin_can_crud_agricultural_products_and_protect_used_products(): void
    {
        $category = ProductCategory::create(['name' => 'Pupuk Organik', 'is_active' => true]);

        $createResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/agricultural-products', [
                'category_id' => $category->id,
                'name' => 'Kompos Kohe Kambing Fermentasi',
                'brand' => 'Tani Subur',
                'active_ingredient' => 'Bahan Organik 45%',
                'unit' => 'kg',
                'description' => 'Pupuk dasar awal musim hujan',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.name', 'Kompos Kohe Kambing Fermentasi')
            ->assertJsonPath('data.category.name', 'Pupuk Organik')
            ->assertJsonPath('data.is_active', true);

        $productId = $createResp->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/agricultural-products?category_id={$category->id}&search=Kompos")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $productId);

        // Reference product in a fertilization record -> delete must be blocked with 422
        $village = Village::create(['name' => 'Desa Produk']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok P1']);
        $garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun P1']);
        $tree = Tree::create(['garden_id' => $garden->id, 'tree_code' => 'P-01', 'status' => 'active']);
        $fert = FertilizationRecord::create([
            'tree_id' => $tree->id,
            'activity_date' => now(),
            'method' => 'tabur',
            'recorded_by' => $this->adminUser->id,
        ]);
        FertilizationItem::create([
            'fertilization_record_id' => $fert->id,
            'product_id' => $productId,
            'quantity' => 5,
            'unit' => 'kg',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/agricultural-products/{$productId}")
            ->assertStatus(422);

        // Unused product can be deleted
        $unused = AgriculturalProduct::create([
            'category_id' => $category->id,
            'name' => 'Produk Sementara',
            'unit' => 'liter',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/agricultural-products/{$unused->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('agricultural_products', ['id' => $unused->id]);
    }

    public function test_farmer_can_view_products_but_cannot_manage_them(): void
    {
        $category = ProductCategory::create(['name' => 'Insektisida', 'is_active' => true]);
        AgriculturalProduct::create([
            'category_id' => $category->id,
            'name' => 'Abamectin 18 EC',
            'unit' => 'ml',
        ]);

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Produk',
            'email' => 'petani.prod@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/product-categories')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/agricultural-products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/product-categories', ['name' => 'Ilegal'])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->postJson('/api/v1/agricultural-products', ['category_id' => $category->id, 'name' => 'Ilegal'])
            ->assertStatus(403);
    }
}
