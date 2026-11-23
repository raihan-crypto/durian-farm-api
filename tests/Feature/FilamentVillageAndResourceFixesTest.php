<?php

namespace Tests\Feature;

use App\Filament\Resources\Gardens\Pages\ManageGardens;
use App\Filament\Resources\Villages\Pages\ManageVillages;
use App\Models\Block;
use App\Models\Garden;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentVillageAndResourceFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected Village $village;

    protected Block $block;

    protected Garden $garden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->superAdmin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'superadmin.fixes@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin.fixes@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($adminRole->id);

        $this->village = Village::create([
            'name' => 'Kemranjen',
            'district' => 'Kemranjen',
            'regency' => 'Kabupaten Banyumas',
            'province' => 'Jawa Tengah',
        ]);

        $this->block = Block::create([
            'village_id' => $this->village->id,
            'name' => 'Blok Kemranjen 01',
            'approval_status' => 'approved',
        ]);

        $this->garden = Garden::create([
            'block_id' => $this->block->id,
            'name' => 'Kebun Duren Kemranjen Utama',
            'status' => 'active',
        ]);
    }

    public function test_manage_villages_page_renders_successfully(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageVillages::class)
            ->assertSuccessful()
            ->assertSee('Kemranjen');
    }

    public function test_user_can_mount_create_village_action_without_closure_type_error(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageVillages::class)
            ->mountAction('create')
            ->assertActionMounted('create');
    }

    public function test_user_can_mount_view_action_on_village_without_closure_type_error(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageVillages::class)
            ->mountTableAction('view', $this->village)
            ->assertSuccessful();
    }

    public function test_user_can_mount_edit_village_action_without_closure_type_error(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageVillages::class)
            ->mountTableAction('edit', $this->village)
            ->assertSuccessful();
    }

    public function test_manage_gardens_can_mount_create_and_edit_without_closure_type_error(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageGardens::class)
            ->assertSuccessful()
            ->mountAction('create')
            ->assertActionMounted('create');

        Livewire::test(ManageGardens::class)
            ->mountTableAction('edit', $this->garden)
            ->assertSuccessful();
    }

    public function test_force_delete_action_is_hidden_on_active_village(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageVillages::class)
            ->assertTableActionVisible('edit', $this->village)
            ->assertTableActionVisible('delete', $this->village)
            ->assertTableActionHidden('restore', $this->village)
            ->assertTableActionHidden('forceDelete', $this->village);
    }

    public function test_force_delete_action_is_visible_on_trashed_village_only_for_super_admin(): void
    {
        $this->village->delete();
        $this->assertTrue($this->village->trashed());

        // For Super Admin
        $this->actingAs($this->superAdmin);
        Livewire::test(ManageVillages::class)
            ->filterTable('trashed', 'only_trashed')
            ->assertTableActionVisible('restore', $this->village)
            ->assertTableActionVisible('forceDelete', $this->village);

        // For regular Admin
        $this->actingAs($this->admin);
        Livewire::test(ManageVillages::class)
            ->filterTable('trashed', 'only_trashed')
            ->assertTableActionHidden('restore', $this->village)
            ->assertTableActionHidden('forceDelete', $this->village);
    }

    public function test_force_delete_is_prevented_when_village_has_blocks(): void
    {
        $this->actingAs($this->superAdmin);
        $this->village->delete();

        // Attempting to force delete halts because child block exists
        Livewire::test(ManageVillages::class)
            ->filterTable('trashed', 'only_trashed')
            ->callTableAction('forceDelete', $this->village)
            ->assertNotified('Gagal Hapus Permanen');

        // Village was NOT permanently deleted
        $this->assertDatabaseHas('villages', ['id' => $this->village->id]);
    }

    public function test_super_admin_can_force_delete_village_when_no_blocks_attached(): void
    {
        $this->actingAs($this->superAdmin);

        $emptyVillage = Village::create([
            'name' => 'Desa Kosong Mandiri',
            'district' => 'Kemranjen',
            'regency' => 'Kabupaten Banyumas',
            'province' => 'Jawa Tengah',
        ]);

        $emptyVillage->delete();

        Livewire::test(ManageVillages::class)
            ->filterTable('trashed', 'only_trashed')
            ->callTableAction('forceDelete', $emptyVillage);

        $this->assertDatabaseMissing('villages', ['id' => $emptyVillage->id]);
    }
}
