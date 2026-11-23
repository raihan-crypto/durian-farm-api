<?php

namespace Tests\Feature;

use App\Filament\Resources\Blocks\Pages\ManageBlocks;
use App\Filament\Resources\Farmers\Pages\ManageFarmers;
use App\Filament\Resources\Gardens\Pages\ManageGardens;
use App\Filament\Resources\Owners\Pages\ManageOwners;
use App\Filament\Resources\Villages\Pages\ManageVillages;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\Owner;
use App\Models\Role;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TerritoryRevisionAndDetailFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $petaniUser;

    protected Owner $petaniOwner;

    protected Owner $otherOwner;

    protected Farmer $workerA;

    protected Farmer $workerB;

    protected Village $villageKemranjen;

    protected Block $blockKemranjen;

    protected Garden $gardenKemranjen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin Revision',
            'email' => 'superadmin.rev@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail()->id);

        $this->petaniUser = User::create([
            'name' => 'Petani Pemilik Kebun',
            'email' => 'petani.rev@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->petaniUser->roles()->attach(Role::where('name', 'petani')->firstOrFail()->id);

        $this->petaniOwner = Owner::create([
            'user_id' => $this->petaniUser->id,
            'owner_type' => 'individual',
            'name' => 'Pak Slamet Petani',
            'phone' => '081234567890',
        ]);

        $this->otherOwner = Owner::create([
            'owner_type' => 'individual',
            'name' => 'Bu Siti Petani',
            'phone' => '081298765432',
        ]);

        $this->workerA = Farmer::create([
            'name' => 'Mas Budi Tenaga Kerja',
            'phone' => '085612345678',
        ]);

        $this->workerB = Farmer::create([
            'name' => 'Mas Agus Tenaga Kerja',
            'phone' => '085687654321',
        ]);

        $this->villageKemranjen = Village::create([
            'name' => 'Alasmalang',
            'district' => 'Kemranjen',
            'regency' => 'Kabupaten Banyumas',
            'province' => 'Jawa Tengah',
        ]);

        $this->blockKemranjen = Block::create([
            'village_id' => $this->villageKemranjen->id,
            'name' => 'Blok Utara',
            'approval_status' => 'approved',
        ]);

        $this->gardenKemranjen = Garden::create([
            'block_id' => $this->blockKemranjen->id,
            'name' => 'Kebun Bawor Utama',
            'ownership_type' => 'individual',
            'status' => 'active',
        ]);

        $this->gardenKemranjen->owners()->attach($this->petaniOwner->id);
        $this->gardenKemranjen->farmers()->attach($this->workerA->id);
    }

    public function test_village_codes_combine_kemendagri_district_code_and_village_order(): void
    {
        // Kemranjen = 06, Alasmalang = 01 => 06.01
        $this->assertSame('06.01', $this->villageKemranjen->village_code);

        // Ajibarang = 14, Ajibarang Kulon = 01 => 14.01 (no collision with 06.01!)
        $ajibarang = Village::create([
            'name' => 'Ajibarang Kulon',
            'district' => 'Ajibarang',
            'regency' => 'Kabupaten Banyumas',
            'province' => 'Jawa Tengah',
        ]);

        $this->assertSame('14.01', $ajibarang->village_code);
        $this->assertNotSame($this->villageKemranjen->village_code, $ajibarang->village_code);
    }

    public function test_block_code_combines_village_code_and_sequence_in_village(): void
    {
        // First block in 06.01 => 06.01.01
        $this->assertSame('06.01.01', $this->blockKemranjen->block_code);

        // Second block in 06.01 => 06.01.02
        $block2 = Block::create([
            'village_id' => $this->villageKemranjen->id,
            'name' => 'Blok Selatan',
            'approval_status' => 'approved',
        ]);
        $this->assertSame('06.01.02', $block2->block_code);
    }

    public function test_duplicate_village_block_and_garden_are_rejected_including_trashed(): void
    {
        // 1. Duplicate active village in same district
        try {
            Village::create([
                'name' => 'alasmalang',
                'district' => 'Kemranjen',
                'regency' => 'Kabupaten Banyumas',
                'province' => 'Jawa Tengah',
            ]);
            $this->fail('Expected ValidationException for duplicate active village.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        // 2. Duplicate trashed village in same district
        $trashedVillage = Village::create([
            'name' => 'Grujugan',
            'district' => 'Kemranjen',
            'regency' => 'Kabupaten Banyumas',
            'province' => 'Jawa Tengah',
        ]);
        $trashedVillage->delete();

        try {
            Village::create([
                'name' => 'Grujugan',
                'district' => 'Kemranjen',
                'regency' => 'Kabupaten Banyumas',
                'province' => 'Jawa Tengah',
            ]);
            $this->fail('Expected ValidationException for duplicate trashed village.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Tempat Sampah', $e->errors()['name'][0]);
        }

        // 3. Duplicate block in same village
        try {
            Block::create([
                'village_id' => $this->villageKemranjen->id,
                'name' => 'Blok Utara',
            ]);
            $this->fail('Expected ValidationException for duplicate block.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        // 4. Duplicate garden in same block
        try {
            Garden::create([
                'block_id' => $this->blockKemranjen->id,
                'name' => 'Kebun Bawor Utama',
            ]);
            $this->fail('Expected ValidationException for duplicate garden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }
    }

    public function test_petani_can_manage_workers_only_on_own_garden(): void
    {
        $otherGarden = Garden::create([
            'block_id' => $this->blockKemranjen->id,
            'name' => 'Kebun Milik Orang Lain',
            'ownership_type' => 'individual',
            'status' => 'active',
        ]);
        $otherGarden->owners()->attach($this->otherOwner->id);

        $this->actingAs($this->petaniUser);

        Livewire::test(ManageGardens::class)
            ->assertTableActionVisible('manage_workers', $this->gardenKemranjen)
            ->assertTableActionHidden('manage_workers', $otherGarden)
            ->callTableAction('manage_workers', $this->gardenKemranjen, data: [
                'farmer_ids' => [$this->workerA->id, $this->workerB->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEqualsCanonicalizing(
            [$this->workerA->id, $this->workerB->id],
            $this->gardenKemranjen->fresh()->farmers()->pluck('farmers.id')->all()
        );

        // Remove worker A, keep only worker B
        Livewire::test(ManageGardens::class)
            ->callTableAction('manage_workers', $this->gardenKemranjen, data: [
                'farmer_ids' => [$this->workerB->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            [$this->workerB->id],
            $this->gardenKemranjen->fresh()->farmers()->pluck('farmers.id')->all()
        );
    }

    public function test_owner_and_farmer_detail_modals_show_bound_gardens_and_blocks(): void
    {
        $this->actingAs($this->superAdmin);

        // Detail Petani action mounts cleanly & view renders bound garden + block
        Livewire::test(ManageOwners::class)
            ->mountTableAction('view', $this->petaniOwner)
            ->assertSuccessful();

        $this->view('filament.modals.owner-detail', ['record' => $this->petaniOwner])
            ->assertSee('Kebun Bawor Utama')
            ->assertSee('Blok Utara')
            ->assertSee('06.01.01');

        // Detail Tenaga Kerja action mounts cleanly & view renders bound garden + block
        Livewire::test(ManageFarmers::class)
            ->mountTableAction('view', $this->workerA)
            ->assertSuccessful();

        $this->view('filament.modals.farmer-detail', ['record' => $this->workerA])
            ->assertSee('Kebun Bawor Utama')
            ->assertSee('Blok Utara')
            ->assertSee('06.01.01');

        // Petani logged in only sees their own profile in ManageOwners
        $this->actingAs($this->petaniUser);
        Livewire::test(ManageOwners::class)
            ->assertCanSeeTableRecords([$this->petaniOwner])
            ->assertCanNotSeeTableRecords([$this->otherOwner]);
    }

    public function test_village_and_block_detail_modals_render_without_village_code_in_village_detail(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageVillages::class)
            ->mountTableAction('view', $this->villageKemranjen)
            ->assertSuccessful();

        $this->view('filament.modals.village-detail', ['record' => $this->villageKemranjen])
            ->assertSee('Desa Alasmalang')
            ->assertSee('Blok Utara')
            ->assertDontSee('Kode Desa');

        Livewire::test(ManageBlocks::class)
            ->mountTableAction('view', $this->blockKemranjen)
            ->assertSuccessful();

        $this->view('filament.modals.block-detail', ['record' => $this->blockKemranjen])
            ->assertSee('06.01.01')
            ->assertSee('Kebun Bawor Utama');

        $this->view('filament.modals.garden-detail', ['record' => $this->gardenKemranjen])
            ->assertSee('Kebun Bawor Utama')
            ->assertSee('Pak Slamet Petani')
            ->assertSee('Mas Budi Tenaga Kerja');
    }

    public function test_regenerate_territory_codes_command_works_in_dry_run_and_force_modes(): void
    {
        $variety = DurianVariety::create([
            'code' => 'BWR',
            'name' => 'Bawor',
            'is_active' => true,
        ]);

        $tree = Tree::create([
            'garden_id' => $this->gardenKemranjen->id,
            'variety_id' => $variety->id,
            'tree_code' => 'BWR-001',
            'status' => 'active',
        ]);

        // Simulate legacy codes
        Village::whereKey($this->villageKemranjen->id)->update(['village_code' => '01']);
        Block::whereKey($this->blockKemranjen->id)->update(['block_code' => '01']);

        // Dry run should NOT modify DB
        $this->artisan('durian:regenerate-territory-codes', ['--dry-run' => true])
            ->assertExitCode(0);
        $this->assertSame('01', $this->villageKemranjen->fresh()->village_code);

        // Force execution updates everything cleanly
        $this->artisan('durian:regenerate-territory-codes', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame('06.01', $this->villageKemranjen->fresh()->village_code);
        $this->assertSame('06.01.01', $this->blockKemranjen->fresh()->block_code);
        $this->assertStringStartsWith('06.01.01.01-BWR-', $tree->fresh()->tree_identity_number);
    }
}
