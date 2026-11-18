<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FilamentAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_admin_login_and_login_page_renders(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->get('/admin/login')
            ->assertStatus(200)
            ->assertSee('Durian Farm Management');
    }

    public function test_super_admin_can_access_dashboard_and_all_core_filament_resources(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $admin = User::create([
            'name' => 'Super Admin Panel',
            'email' => 'panel.admin@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $admin->roles()->attach($superAdminRole->id);

        $this->actingAs($admin);

        $this->get('/admin')
            ->assertStatus(200)
            ->assertSee('Durian Farm Management')
            ->assertSee('Wilayah &amp; Kepemilikan', false);
        $this->get('/admin/villages')->assertStatus(200);
        $this->get('/admin/blocks')->assertStatus(200);
        $this->get('/admin/gardens')->assertStatus(200);
        $this->get('/admin/owners')->assertStatus(200);
        $this->get('/admin/farmers')->assertStatus(200);
        $this->get('/admin/durian-varieties')->assertStatus(200);
        $this->get('/admin/trees')->assertStatus(200);
        $this->get('/admin/tree-measurements')->assertStatus(200);
        $this->get('/admin/product-categories')->assertStatus(200);
        $this->get('/admin/agricultural-products')->assertStatus(200);
        $this->get('/admin/fertilization-records')->assertStatus(200);
        $this->get('/admin/spraying-records')->assertStatus(200);
        $this->get('/admin/flowering-records')->assertStatus(200);
        $this->get('/admin/fruit-development-records')->assertStatus(200);
        $this->get('/admin/harvest-seasons')->assertStatus(200);
        $this->get('/admin/harvest-sessions')->assertStatus(200);
        $this->get('/admin/harvest-records')->assertStatus(200);
        $this->get('/admin/organizations')->assertStatus(200);
        $this->get('/admin/users')->assertStatus(200);
        $this->get('/admin/roles')->assertStatus(200);
        $this->get('/admin/audit-logs')->assertStatus(200);
        $this->get('/admin/sync-logs')->assertStatus(200);
    }

    public function test_farmer_and_inactive_admin_cannot_access_filament_admin_panel(): void
    {
        $farmerRole = Role::where('name', 'farmer')->firstOrFail();
        $farmer = User::create([
            'name' => 'Petani Mobile Only',
            'email' => 'farmer.nopanel@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $farmer->roles()->attach($farmerRole->id);

        $this->actingAs($farmer)->get('/admin')->assertStatus(403);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $inactiveAdmin = User::create([
            'name' => 'Admin Nonaktif',
            'email' => 'inactive.admin@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);
        $inactiveAdmin->roles()->attach($adminRole->id);

        $this->actingAs($inactiveAdmin)->get('/admin')->assertStatus(403);
    }
}
