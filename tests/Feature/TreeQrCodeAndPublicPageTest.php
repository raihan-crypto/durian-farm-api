<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Models\Owner;
use App\Models\Role;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use App\Services\TreeQrCodeService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TreeQrCodeAndPublicPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Tree $tree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

        $this->adminUser = User::create([
            'name' => 'Admin QR',
            'email' => 'admin.qr@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create([
            'name' => 'Desa Cikidang',
            'district' => 'Cikidang',
            'regency' => 'Sukabumi',
            'province' => 'Jawa Barat',
        ]);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok QR']);
        $garden = Garden::create(['block_id' => $block->id, 'name' => 'Kebun Wisata Durian']);

        // Sensitive owner & farmer records that must NEVER appear on public responses
        $owner = Owner::create([
            'owner_type' => 'individual',
            'name' => 'Haji Rahasia Pemilik',
            'phone' => '081299990000',
            'address' => 'Alamat Pribadi Rahasia',
        ]);
        GardenOwner::create([
            'garden_id' => $garden->id,
            'owner_id' => $owner->id,
            'ownership_percentage' => 100,
        ]);

        $farmer = Farmer::create([
            'name' => 'Petani Rahasia',
            'phone' => '081388887777',
        ]);
        GardenFarmer::create([
            'garden_id' => $garden->id,
            'farmer_id' => $farmer->id,
        ]);

        $variety = DurianVariety::create([
            'code' => 'OC',
            'name' => 'Ochee / Duri Hitam',
            'origin' => 'Pulau Pinang',
            'description' => 'Varietas unggulan dengan aroma khas.',
            'characteristics' => 'Daging oranye kemerahan, rasa creamy manis pahit.',
            'is_active' => true,
        ]);

        $this->tree = Tree::create([
            'garden_id' => $garden->id,
            'variety_id' => $variety->id,
            'tree_code' => 'OC-009',
            'tree_name' => 'Pohon Duri Hitam #9',
            'planted_at' => '2022-01-15',
            'latitude' => -6.9112233,
            'longitude' => 106.8223344,
            'status' => 'active',
            'notes' => 'Catatan internal rahasia',
        ]);

        TreeMeasurement::create([
            'tree_id' => $this->tree->id,
            'measured_at' => '2026-10-01 10:00:00',
            'diameter_cm' => 24.5,
            'height_m' => 5.4,
            'canopy_width_m' => 4.2,
            'notes' => 'Catatan ukur internal',
            'recorded_by' => $this->adminUser->id,
        ]);
    }

    public function test_qr_code_generation_image_rendering_and_public_privacy_filtering(): void
    {
        $qrResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees/{$this->tree->id}/qr-code");

        $qrResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.uses_default_public_fields', true);

        $token = $qrResp->json('data.public_token');
        $this->assertSame(TreeQrCodeService::TOKEN_LENGTH, strlen($token));
        $this->assertNotSame($this->tree->id, $token);

        // SVG image rendering
        $svgResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->get("/api/v1/trees/{$this->tree->id}/qr-code/image?format=svg");
        $svgResp->assertStatus(200);
        $this->assertStringContainsString('image/svg+xml', (string) $svgResp->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $svgResp->getContent());

        // PNG image rendering
        $pngResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->get("/api/v1/trees/{$this->tree->id}/qr-code/image?format=png&download=1");
        $pngResp->assertStatus(200);
        $this->assertStringContainsString('image/png', (string) $pngResp->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $pngResp->headers->get('Content-Disposition'));

        // Public API endpoint (no auth required)
        $publicApi = $this->getJson("/api/v1/public/trees/{$token}");
        $publicApi->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tree_code', 'OC-009')
            ->assertJsonPath('data.variety.name', 'Ochee / Duri Hitam')
            ->assertJsonPath('data.garden.name', 'Kebun Wisata Durian')
            ->assertJsonPath('data.general_location.village', 'Desa Cikidang')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.latitude')
            ->assertJsonMissingPath('data.longitude')
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.latest_measurement');

        // Verify sensitive strings never appear anywhere in JSON or Blade HTML
        $rawJson = $publicApi->getContent();
        $this->assertStringNotContainsString('Haji Rahasia Pemilik', $rawJson);
        $this->assertStringNotContainsString('081299990000', $rawJson);
        $this->assertStringNotContainsString('Petani Rahasia', $rawJson);
        $this->assertStringNotContainsString('Catatan internal rahasia', $rawJson);
        $this->assertStringNotContainsString($this->tree->id, $rawJson);

        // Public Blade page renders cleanly
        $pageResp = $this->get("/t/{$token}");
        $pageResp->assertStatus(200)
            ->assertSee('OC-009')
            ->assertSee('Ochee / Duri Hitam')
            ->assertSee('Kebun Wisata Durian')
            ->assertDontSee('Haji Rahasia Pemilik')
            ->assertDontSee('081299990000')
            ->assertDontSee('Catatan internal rahasia');
    }

    public function test_admin_can_configure_public_fields_deactivate_and_regenerate_qr_code(): void
    {
        $initialResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/trees/{$this->tree->id}/qr-code");
        $originalToken = $initialResp->json('data.public_token');

        // Configure custom public fields (enable latest_measurement & age, hide garden)
        $updateResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/trees/{$this->tree->id}/qr-code", [
                'public_fields' => ['tree_code', 'variety', 'age', 'status', 'latest_measurement'],
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.uses_default_public_fields', false);

        $publicResp = $this->getJson("/api/v1/public/trees/{$originalToken}");
        $publicResp->assertStatus(200)
            ->assertJsonPath('data.tree_code', 'OC-009')
            ->assertJsonPath('data.status', 'Aktif')
            ->assertJsonPath('data.latest_measurement.height_m', 5.4)
            ->assertJsonMissingPath('data.garden');

        // Deactivate QR Code -> public API and page both return 404
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/v1/trees/{$this->tree->id}/qr-code", [
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->getJson("/api/v1/public/trees/{$originalToken}")->assertStatus(404);
        $this->get("/t/{$originalToken}")->assertStatus(404);

        // Regenerate QR Code -> new token works, old token stays invalid
        $regenResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/api/v1/trees/{$this->tree->id}/qr-code/regenerate");

        $regenResp->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $newToken = $regenResp->json('data.public_token');
        $this->assertNotSame($originalToken, $newToken);

        $this->getJson("/api/v1/public/trees/{$originalToken}")->assertStatus(404);
        $this->getJson("/api/v1/public/trees/{$newToken}")->assertStatus(200);
        $this->get("/t/{$newToken}")->assertStatus(200);
    }

    public function test_invalid_token_returns_404_and_public_endpoint_is_rate_limited(): void
    {
        config(['durian.public_tree.rate_limit_per_minute' => 2]);

        $this->getJson('/api/v1/public/trees/short-invalid')->assertStatus(404);
        $this->get('/t/short-invalid')->assertStatus(404);

        $token = str_repeat('a', TreeQrCodeService::TOKEN_LENGTH);

        $this->getJson("/api/v1/public/trees/{$token}")->assertStatus(429);
    }
}
