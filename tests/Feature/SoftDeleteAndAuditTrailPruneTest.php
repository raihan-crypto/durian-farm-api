<?php

namespace Tests\Feature;

use App\Models\ActivityPhoto;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SoftDeleteAndAuditTrailPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Storage::fake('public');
    }

    public function test_prune_command_permanently_deletes_eligible_records_and_creates_audit_log(): void
    {
        $village = Village::create([
            'name' => 'Desa Terhapus Lama',
            'district' => 'Kecamatan X',
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);
        $village->delete();

        // Simulate soft deleted 45 days ago
        $village->deleted_at = Carbon::now()->subDays(45);
        $village->save();

        $this->assertSoftDeleted('villages', ['id' => $village->id]);

        // Run pruning command with default 30 days retention
        $exitCode = Artisan::call('durian:prune-soft-deleted');
        $this->assertSame(0, $exitCode);

        // Record should be permanently gone from villages table
        $this->assertDatabaseMissing('villages', ['id' => $village->id]);

        // Traceable audit log should exist
        $audit = AuditLog::where('table_name', 'villages')
            ->where('record_id', $village->id)
            ->where('action', 'pruned')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Desa Terhapus Lama', $audit->old_values['name'] ?? null);
        $this->assertSame('durian:prune-soft-deleted', $audit->new_values['pruned_by'] ?? null);
    }

    public function test_prune_command_preserves_records_newer_than_retention_days(): void
    {
        $village = Village::create([
            'name' => 'Desa Terhapus Baru',
            'district' => 'Kecamatan Y',
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);
        $village->delete();

        // Soft deleted 10 days ago (within 30 days retention)
        $village->deleted_at = Carbon::now()->subDays(10);
        $village->save();

        Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);

        // Still exists as soft-deleted
        $this->assertSoftDeleted('villages', ['id' => $village->id]);
        $this->assertDatabaseHas('villages', ['id' => $village->id]);
    }

    public function test_prune_command_skips_records_with_dependencies_to_protect_referential_integrity(): void
    {
        $village = Village::create([
            'name' => 'Desa Induk Memiliki Blok',
            'district' => 'Kecamatan Z',
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);

        $block = Block::create([
            'village_id' => $village->id,
            'name' => 'Blok Anak',
            'approval_status' => 'approved',
        ]);

        // Soft delete village 45 days ago, but active block still exists
        $village->delete();
        $village->deleted_at = Carbon::now()->subDays(45);
        $village->save();

        Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);

        // Village MUST NOT be permanently deleted because child block exists!
        $this->assertSoftDeleted('villages', ['id' => $village->id]);
        $this->assertDatabaseHas('villages', ['id' => $village->id]);
    }

    public function test_prune_command_deletes_activity_photo_files_from_storage(): void
    {
        $photoPath = 'photos/test_prune.jpg';
        Storage::disk('public')->put($photoPath, 'dummy-photo-content');

        $photo = ActivityPhoto::create([
            'activity_type' => 'fertilization_record',
            'activity_id' => (string) Str::uuid(),
            'file_path' => $photoPath,
            'file_name' => 'test_prune.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 1024,
        ]);

        $photo->delete();
        $photo->deleted_at = Carbon::now()->subDays(35);
        $photo->save();

        $this->assertTrue(Storage::disk('public')->exists($photoPath));

        Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);

        $this->assertDatabaseMissing('activity_photos', ['id' => $photo->id]);
        $this->assertFalse(Storage::disk('public')->exists($photoPath));
    }

    public function test_prune_command_is_safe_to_run_repeatedly(): void
    {
        $village = Village::create([
            'name' => 'Desa Uji Idempoten',
            'district' => 'Kecamatan Idem',
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);
        $village->delete();
        $village->deleted_at = Carbon::now()->subDays(50);
        $village->save();

        // First run
        $firstExit = Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);
        $this->assertSame(0, $firstExit);
        $this->assertDatabaseMissing('villages', ['id' => $village->id]);

        // Second run immediately afterwards (no-op)
        $secondExit = Artisan::call('durian:prune-soft-deleted', ['--days' => 30]);
        $this->assertSame(0, $secondExit);
    }
}
