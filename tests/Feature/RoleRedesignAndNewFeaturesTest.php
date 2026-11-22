<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use App\Services\TerritoryScopeService;
use App\Support\BanyumasRegionData;
use Database\Seeders\RbacSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleRedesignAndNewFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $petani;

    protected User $tenagaKerja;

    protected User $pemerintahDesa;

    protected Village $villageBanyumas;

    protected Block $block1;

    protected Garden $garden1;

    protected DurianVariety $varietyBawor;

    protected DurianVariety $varietyMusangKing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $org = Organization::create(['name' => 'Perkebunan Banyumas Sejahtera']);

        $this->villageBanyumas = Village::create([
            'name' => 'Kemranjen',
            'district' => 'Kemranjen',
            'regency' => 'Kabupaten Banyumas',
            'province' => 'Jawa Tengah',
        ]);

        $this->block1 = Block::create([
            'village_id' => $this->villageBanyumas->id,
            'name' => 'Blok Bawor Utama',
            'approval_status' => 'approved',
        ]);

        $this->garden1 = Garden::create([
            'block_id' => $this->block1->id,
            'name' => 'Kebun Durian Kemranjen A',
            'status' => 'active',
        ]);

        $this->varietyBawor = DurianVariety::create([
            'code' => 'BWR',
            'name' => 'Durian Bawor Banyumas',
            'is_active' => true,
        ]);

        $this->varietyMusangKing = DurianVariety::create([
            'code' => 'MK',
            'name' => 'Durian Musang King',
            'is_active' => true,
        ]);

        // Users
        $this->superAdmin = User::create([
            'organization_id' => $org->id,
            'name' => 'Super Administrator',
            'email' => 'superadmin_new@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach(Role::where('name', 'super_admin')->first());

        $this->admin = User::create([
            'organization_id' => $org->id,
            'name' => 'Admin Perkebunan',
            'email' => 'admin_new@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::where('name', 'admin')->first());

        $this->petani = User::create([
            'organization_id' => $org->id,
            'name' => 'Petani Pengelola',
            'email' => 'petani_pengelola@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->petani->roles()->attach(Role::where('name', 'petani')->first());
        $ownerProfile = Owner::create([
            'user_id' => $this->petani->id,
            'name' => 'Petani Pengelola',
            'owner_type' => 'individual',
        ]);
        GardenOwner::create([
            'garden_id' => $this->garden1->id,
            'owner_id' => $ownerProfile->id,
            'ownership_percentage' => 100,
        ]);

        $this->tenagaKerja = User::create([
            'organization_id' => $org->id,
            'name' => 'Tenaga Kerja Lapangan',
            'email' => 'tenaga_kerja@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->tenagaKerja->roles()->attach(Role::where('name', 'tenaga_kerja')->first());
        $farmerProfile = Farmer::create([
            'user_id' => $this->tenagaKerja->id,
            'name' => 'Tenaga Kerja Lapangan',
        ]);
        GardenFarmer::create([
            'garden_id' => $this->garden1->id,
            'farmer_id' => $farmerProfile->id,
            'assignment_date' => now()->toDateString(),
        ]);

        $this->pemerintahDesa = User::create([
            'organization_id' => $org->id,
            'village_id' => $this->villageBanyumas->id,
            'name' => 'Perangkat Desa Kemranjen',
            'email' => 'desa_kemranjen@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->pemerintahDesa->roles()->attach(Role::where('name', 'pemerintah_desa')->first());
    }

    /**
     * Test role access segregation: Web panel vs Mobile app.
     */
    public function test_role_access_segregation_web_vs_mobile(): void
    {
        $panel = new Panel;

        // Super Admin, Admin, Petani, and Pemerintah Desa CAN access Web Panel
        $this->assertTrue($this->superAdmin->canAccessPanel($panel));
        $this->assertTrue($this->admin->canAccessPanel($panel));
        $this->assertTrue($this->petani->canAccessPanel($panel));
        $this->assertTrue($this->pemerintahDesa->canAccessPanel($panel));

        // Tenaga Kerja CANNOT access Web Panel (mobile app only)
        $this->assertFalse($this->tenagaKerja->canAccessPanel($panel));
    }

    /**
     * Test territory scoping for Pemerintah Desa and Petani.
     */
    public function test_territory_scoping_isolation(): void
    {
        $scopeService = app(TerritoryScopeService::class);

        // 1. Pemerintah Desa is strictly scoped to their bound village
        $desaVillages = $scopeService->getAccessibleVillageIds($this->pemerintahDesa);
        $this->assertCount(1, $desaVillages);
        $this->assertSame($this->villageBanyumas->id, $desaVillages[0]);

        $desaBlocks = $scopeService->getAccessibleBlockIds($this->pemerintahDesa);
        $this->assertContains($this->block1->id, $desaBlocks);

        // Another village must NOT be accessible
        $otherVillage = Village::create(['name' => 'Desa Tambak', 'district' => 'Tambak']);
        $this->assertFalse($scopeService->canAccessVillage($this->pemerintahDesa, $otherVillage->id));

        // 2. Petani (pengelola kebun) is scoped to their owned garden
        $petaniGardens = $scopeService->getAccessibleGardenIds($this->petani);
        $this->assertContains($this->garden1->id, $petaniGardens);

        $otherGarden = Garden::create(['block_id' => $this->block1->id, 'name' => 'Kebun Milik Orang Lain']);
        $this->assertFalse($scopeService->canAccessGarden($this->petani, $otherGarden->id));
    }

    /**
     * Test Banyumas reference data cascading and auto village code.
     */
    public function test_banyumas_reference_data_and_auto_village_code(): void
    {
        $districts = BanyumasRegionData::getDistricts();
        $this->assertCount(27, $districts);
        $this->assertContains('Kemranjen', $districts);
        $this->assertContains('Baturraden', $districts);

        $villages = BanyumasRegionData::getVillagesForDistrict('Kemranjen');
        $this->assertNotEmpty($villages);

        // Village code is automatically set (2 digits)
        $this->assertNotNull($this->villageBanyumas->village_code);
        $this->assertSame(2, strlen($this->villageBanyumas->village_code));
    }

    /**
     * Test Block code and Garden code auto generation.
     */
    public function test_block_and_garden_code_auto_generation(): void
    {
        // Block code auto-generated as 2 digits
        $this->assertNotNull($this->block1->block_code);
        $this->assertSame(2, strlen($this->block1->block_code));

        // Garden code auto-generated as 2 digits
        $this->assertNotNull($this->garden1->garden_code);
        $this->assertSame(2, strlen($this->garden1->garden_code));
        $this->assertSame('active', $this->garden1->status);
    }

    /**
     * Test Block approval workflow for Pemerintah Desa requests.
     */
    public function test_block_approval_workflow(): void
    {
        $tokenDesa = $this->pemerintahDesa->createToken('desa')->plainTextToken;
        $tokenAdmin = $this->admin->createToken('admin')->plainTextToken;

        // 1. Pemerintah desa requests a new block -> status pending_addition
        auth()->forgetGuards();
        $createResp = $this->withHeader('Authorization', 'Bearer '.$tokenDesa)
            ->postJson('/api/v1/blocks', [
                'village_id' => $this->villageBanyumas->id,
                'name' => 'Blok Usulan Warga',
                'area_hectares' => 2.5,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.approval_status', 'pending_addition');

        $blockId = $createResp->json('data.id');
        $block = Block::find($blockId);
        $this->assertSame('pending_addition', $block->approval_status);
        $this->assertSame($this->pemerintahDesa->id, $block->requested_by);

        // 2. Admin approves the block addition -> status approved
        auth()->forgetGuards();
        $approveResp = $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->postJson("/api/v1/blocks/{$blockId}/approve");

        $approveResp->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'approved');

        $this->assertSame('approved', $block->fresh()->approval_status);

        // 3. Pemerintah desa requests deletion of the block -> status pending_deletion
        auth()->forgetGuards();
        $deleteRequestResp = $this->withHeader('Authorization', 'Bearer '.$tokenDesa)
            ->deleteJson("/api/v1/blocks/{$blockId}");

        $deleteRequestResp->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'pending_deletion');

        $this->assertSame('pending_deletion', $block->fresh()->approval_status);

        // 4. Admin approves block deletion -> block is soft deleted
        auth()->forgetGuards();
        $approveDeleteResp = $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->postJson("/api/v1/blocks/{$blockId}/approve");

        $approveDeleteResp->assertStatus(200);
        $this->assertSoftDeleted('blocks', ['id' => $blockId]);
    }

    /**
     * Test Tree NIK auto generation and dynamic recalculation on garden/variety change.
     */
    public function test_tree_nik_auto_generation_and_dynamic_recalculation(): void
    {
        Storage::fake('public');

        // Create tree in Garden Kemranjen A (Variety: Bawor BWR)
        $tree = Tree::create([
            'garden_id' => $this->garden1->id,
            'variety_id' => $this->varietyBawor->id,
            'tree_code' => 'KMR-BWR-001',
            'tree_name' => 'Pohon Induk Bawor 1',
            'status' => 'active',
        ]);

        $nikOriginal = $tree->tree_identity_number;
        $this->assertNotEmpty($nikOriginal);

        // NIK format: [KodeDesa 2-digit]-[KodeBlok 2-digit]-[KodeKebun 2-digit]-[KodeVarietas]-[Urut 4-digit]
        // E.g. 01-01-01-BWR-0001
        $parts = explode('-', $nikOriginal);
        $this->assertCount(5, $parts);
        $this->assertSame($this->villageBanyumas->village_code, $parts[0]);
        $this->assertSame($this->block1->block_code, $parts[1]);
        $this->assertSame($this->garden1->garden_code, $parts[2]);
        $this->assertSame('BWR', $parts[3]);
        $this->assertSame('0001', $parts[4]);

        // Change tree variety to Musang King (MK) -> NIK dynamically updates!
        $tree->update([
            'variety_id' => $this->varietyMusangKing->id,
        ]);

        $tree->refresh();
        $this->assertNotSame($nikOriginal, $tree->tree_identity_number);
        $this->assertStringContainsString('-MK-', $tree->tree_identity_number);
    }

    /**
     * Test optional tree photo upload via API.
     */
    public function test_optional_tree_photo_upload(): void
    {
        Storage::fake('public');
        $tokenAdmin = $this->admin->createToken('admin')->plainTextToken;

        $photoFile = UploadedFile::fake()->image('durian_tree.jpg', 600, 600);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->post('/api/v1/trees', [
                'garden_id' => $this->garden1->id,
                'variety_id' => $this->varietyBawor->id,
                'tree_code' => 'KMR-BWR-PHOTO',
                'tree_name' => 'Pohon Bawor Berfoto',
                'photo' => $photoFile,
            ]);

        $response->assertStatus(201);
        $photoPath = $response->json('data.photo_path');
        $this->assertNotEmpty($photoPath);
        Storage::disk('public')->assertExists($photoPath);
    }

    /**
     * Test Global Soft Deletes, Super Admin Restore (Putback), and 30-Day Auto Pruning.
     */
    public function test_global_soft_deletes_restore_and_30_day_auto_pruning(): void
    {
        // 1. Soft delete a garden
        $garden = Garden::create([
            'block_id' => $this->block1->id,
            'name' => 'Kebun Cadangan',
            'status' => 'inactive',
        ]);

        $garden->delete();
        $this->assertSoftDeleted('gardens', ['id' => $garden->id]);

        // 2. Super Admin can restore (putback)
        $garden->restore();
        $this->assertDatabaseHas('gardens', [
            'id' => $garden->id,
            'deleted_at' => null,
        ]);

        // 3. Test 30-day Prune Command
        // Record deleted 5 days ago (should NOT be pruned)
        $recentDeleted = Garden::create([
            'block_id' => $this->block1->id,
            'name' => 'Kebun Dihapus Baru',
        ]);
        $recentDeleted->delete();
        Garden::withTrashed()->where('id', $recentDeleted->id)->update([
            'deleted_at' => Carbon::now()->subDays(5),
        ]);

        // Record deleted 35 days ago (SHOULD be permanently deleted)
        $oldDeleted = Garden::create([
            'block_id' => $this->block1->id,
            'name' => 'Kebun Dihapus Lama',
        ]);
        $oldDeleted->delete();
        Garden::withTrashed()->where('id', $oldDeleted->id)->update([
            'deleted_at' => Carbon::now()->subDays(35),
        ]);

        // Run the prune command
        Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);

        // Recent deleted record remains in database (soft-deleted)
        $this->assertSoftDeleted('gardens', ['id' => $recentDeleted->id]);

        // Old deleted record is permanently removed from database
        $this->assertDatabaseMissing('gardens', ['id' => $oldDeleted->id]);
    }
}
