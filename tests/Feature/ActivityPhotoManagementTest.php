<?php

namespace Tests\Feature;

use App\Models\ActivityPhoto;
use App\Models\Block;
use App\Models\Farmer;
use App\Models\FertilizationRecord;
use App\Models\Garden;
use App\Models\GardenFarmer;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityPhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Garden $gardenA;

    protected Garden $gardenB;

    protected FertilizationRecord $activityA;

    protected FertilizationRecord $activityB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->adminUser = User::create([
            'name' => 'Admin Foto',
            'email' => 'admin.photo@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Foto']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok Foto']);
        $this->gardenA = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Foto A']);
        $this->gardenB = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Foto B']);

        $treeA = Tree::create(['garden_id' => $this->gardenA->id, 'tree_code' => 'FA-01', 'status' => 'active']);
        $treeB = Tree::create(['garden_id' => $this->gardenB->id, 'tree_code' => 'FB-01', 'status' => 'active']);

        $this->activityA = FertilizationRecord::create([
            'tree_id' => $treeA->id,
            'activity_date' => now(),
            'method' => 'tabur',
            'recorded_by' => $this->adminUser->id,
        ]);

        $this->activityB = FertilizationRecord::create([
            'tree_id' => $treeB->id,
            'activity_date' => now(),
            'method' => 'kocor',
            'recorded_by' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_upload_list_view_and_delete_activity_photos_with_polymorphic_validation(): void
    {
        Storage::fake('public');

        // Reject nonexistent polymorphic target
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post('/api/v1/activity-photos', [
                'activity_type' => 'fertilization',
                'activity_id' => (string) Str::uuid(),
                'photo' => UploadedFile::fake()->image('missing.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        // Upload valid photo
        $uploadResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->post('/api/v1/activity-photos', [
                'activity_type' => 'fertilization',
                'activity_id' => $this->activityA->id,
                'photo' => UploadedFile::fake()->image('pemupukan.jpg', 500, 500),
                'caption' => 'Proses tabur pupuk',
            ], ['Accept' => 'application/json']);

        $uploadResp->assertStatus(201)
            ->assertJsonPath('data.activity_type', 'fertilization')
            ->assertJsonPath('data.activity_id', $this->activityA->id)
            ->assertJsonPath('data.caption', 'Proses tabur pupuk');

        $photoId = $uploadResp->json('data.id');
        $photo = ActivityPhoto::findOrFail($photoId);
        Storage::disk('public')->assertExists($photo->file_path);
        $this->assertNotNull($photo->thumbnail_path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);
        $this->assertEquals('image/webp', $photo->mime_type);
        $this->assertGreaterThan(0, $photo->file_size_bytes);
        $this->assertGreaterThan(0, $photo->width);
        $this->assertGreaterThan(0, $photo->height);
        $this->assertNotNull($uploadResp->json('data.thumbnail_url'));

        // List by activity target
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/activity-photos?activity_type=fertilization&activity_id={$this->activityA->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Show single photo
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/activity-photos/{$photoId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $photoId);

        // Delete photo removes record and stored file
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/activity-photos/{$photoId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('activity_photos', ['id' => $photoId]);
        Storage::disk('public')->assertMissing($photo->file_path);
    }

    public function test_farmer_photo_access_is_scoped_by_territory_and_uploader_ownership(): void
    {
        Storage::fake('public');

        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmerUser = User::create([
            'name' => 'Petani Foto A',
            'email' => 'petani.foto@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmerUser->roles()->attach($farmerRole->id);
        $farmerToken = $farmerUser->createToken('farmer-token')->plainTextToken;

        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'name' => 'Petani Foto A']);
        GardenFarmer::create([
            'garden_id' => $this->gardenA->id,
            'farmer_id' => $farmer->id,
            'assignment_date' => Carbon::today()->subDays(2)->toDateString(),
        ]);

        // Seed one photo uploaded by admin on activityA and one on activityB
        $adminPhotoOnA = ActivityPhoto::create([
            'activity_type' => 'fertilization',
            'activity_id' => $this->activityA->id,
            'file_path' => 'activity-photos/fertilization/admin-a.jpg',
            'file_name' => 'admin-a.jpg',
            'uploaded_by' => $this->adminUser->id,
        ]);
        $adminPhotoOnB = ActivityPhoto::create([
            'activity_type' => 'fertilization',
            'activity_id' => $this->activityB->id,
            'file_path' => 'activity-photos/fertilization/admin-b.jpg',
            'file_name' => 'admin-b.jpg',
            'uploaded_by' => $this->adminUser->id,
        ]);

        // Farmer cannot enumerate global photos without activity_type + activity_id
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson('/api/v1/activity-photos')
            ->assertStatus(422);

        // Farmer cannot view or upload photos on unassigned garden B
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->getJson("/api/v1/activity-photos/{$adminPhotoOnB->id}")
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->post('/api/v1/activity-photos', [
                'activity_type' => 'fertilization',
                'activity_id' => $this->activityB->id,
                'photo' => UploadedFile::fake()->image('ilegal.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(403);

        // Farmer can upload photo on assigned garden A and delete their own upload
        $ownUpload = $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->post('/api/v1/activity-photos', [
                'activity_type' => 'fertilization',
                'activity_id' => $this->activityA->id,
                'photo' => UploadedFile::fake()->image('petani.jpg'),
            ], ['Accept' => 'application/json']);

        $ownUpload->assertStatus(201);
        $ownPhotoId = $ownUpload->json('data.id');

        // Farmer CANNOT delete admin's photo even on garden A
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->deleteJson("/api/v1/activity-photos/{$adminPhotoOnA->id}")
            ->assertStatus(403);

        // Farmer CAN delete their own photo on garden A
        $this->withHeader('Authorization', 'Bearer '.$farmerToken)
            ->deleteJson("/api/v1/activity-photos/{$ownPhotoId}")
            ->assertStatus(200);
    }
}
