<?php

namespace Tests\Feature;

use App\Filament\Pages\TrashBin;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrashBinSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularAdmin;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->org = Organization::create([
            'name' => 'TrashBin Test Org',
            'code' => 'TB-ORG',
            'status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@durian.test',
            'password' => 'Password123!',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        $this->regularAdmin = User::create([
            'name' => 'Regular Admin',
            'email' => 'admin@durian.test',
            'password' => 'Password123!',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $this->regularAdmin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
    }

    public function test_non_super_admin_cannot_access_trash_bin(): void
    {
        $this->actingAs($this->regularAdmin);
        $this->assertFalse(TrashBin::canAccess());

        $village = Village::create([
            'name' => 'Desa Non Super',
            'district' => 'Kecamatan A',
            'regency' => 'Kabupaten B',
            'province' => 'Provinsi C',
        ]);
        $village->delete();

        $page = new TrashBin;
        $page->forceDeleteRecord(Village::class, $village->id);

        // Record must not be force-deleted
        $this->assertSoftDeleted('villages', ['id' => $village->id]);
    }

    public function test_class_outside_allowed_models_is_rejected(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(TrashBin::class)
            ->call('forceDeleteRecord', 'App\\Models\\NonExistentClass', 'some-id')
            ->assertNotified('Akses Ditolak');

        Livewire::test(TrashBin::class)
            ->call('restoreRecord', 'App\\Models\\NonExistentClass', 'some-id')
            ->assertNotified('Akses Ditolak');
    }

    public function test_force_delete_is_rejected_when_record_has_active_dependencies(): void
    {
        $this->actingAs($this->superAdmin);

        $village = Village::create([
            'name' => 'Desa Induk',
            'district' => 'Kecamatan D',
            'regency' => 'Kabupaten E',
            'province' => 'Provinsi F',
        ]);

        $block = Block::create([
            'village_id' => $village->id,
            'name' => 'Blok Dependen',
            'block_code' => 'BLK-DEP-01',
            'area_hectares' => 5.0,
        ]);

        $garden = Garden::create([
            'block_id' => $block->id,
            'name' => 'Kebun Dependen',
            'garden_code' => 'KBN-DEP-01',
            'area_hectares' => 2.0,
        ]);

        $variety = DurianVariety::create([
            'name' => 'Musang King Dep',
            'code' => 'MK-DEP',
        ]);

        $tree = Tree::create([
            'garden_id' => $garden->id,
            'variety_id' => $variety->id,
            'tree_code' => 'KBN-DEP-01-MK-001',
            'status' => 'active',
        ]);

        TreeMeasurement::create([
            'tree_id' => $tree->id,
            'measured_at' => now(),
            'height_meters' => 3.5,
            'trunk_diameter_cm' => 15.0,
        ]);

        // Soft delete tree and garden
        $tree->delete();
        $garden->delete();

        // 1. Try to force delete tree that still has tree_measurements
        Livewire::test(TrashBin::class)
            ->call('forceDeleteRecord', Tree::class, $tree->id)
            ->assertNotified('Gagal Hapus Permanen');

        $this->assertSoftDeleted('trees', ['id' => $tree->id]);

        // 2. Try to force delete garden that still has trees
        Livewire::test(TrashBin::class)
            ->call('forceDeleteRecord', Garden::class, $garden->id)
            ->assertNotified('Gagal Hapus Permanen');

        $this->assertSoftDeleted('gardens', ['id' => $garden->id]);
    }

    public function test_force_delete_succeeds_and_creates_audit_log_when_no_dependencies(): void
    {
        $this->actingAs($this->superAdmin);

        $village = Village::create([
            'name' => 'Desa Tanpa Blok',
            'district' => 'Kecamatan G',
            'regency' => 'Kabupaten H',
            'province' => 'Provinsi I',
        ]);
        $village->delete();

        $this->assertSoftDeleted('villages', ['id' => $village->id]);

        Livewire::test(TrashBin::class)
            ->call('forceDeleteRecord', Village::class, $village->id)
            ->assertNotified('Data telah dihapus permanen');

        $this->assertDatabaseMissing('villages', ['id' => $village->id]);

        $audit = AuditLog::where('table_name', 'villages')
            ->where('record_id', $village->id)
            ->where('action', 'force_deleted')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->superAdmin->id, $audit->user_id);
        $this->assertSame('filament_trash_bin', $audit->new_values['source'] ?? null);
    }

    public function test_restore_record_succeeds_and_creates_audit_log(): void
    {
        $this->actingAs($this->superAdmin);

        $village = Village::create([
            'name' => 'Desa Akan Dipulihkan',
            'district' => 'Kecamatan J',
            'regency' => 'Kabupaten K',
            'province' => 'Provinsi L',
        ]);
        $village->delete();

        $this->assertSoftDeleted('villages', ['id' => $village->id]);

        Livewire::test(TrashBin::class)
            ->call('restoreRecord', Village::class, $village->id)
            ->assertNotified();

        $this->assertDatabaseHas('villages', [
            'id' => $village->id,
            'deleted_at' => null,
        ]);

        $audit = AuditLog::where('table_name', 'villages')
            ->where('record_id', $village->id)
            ->where('action', 'restored')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->superAdmin->id, $audit->user_id);
    }
}
