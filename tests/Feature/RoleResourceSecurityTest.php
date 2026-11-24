<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleResourceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->org = Organization::create([
            'name' => 'Role Security Test Org',
            'code' => 'RST-ORG',
            'status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@role-test.com',
            'password' => 'Password123!',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        $this->admin = User::create([
            'name' => 'Regular Admin',
            'email' => 'admin@role-test.com',
            'password' => 'Password123!',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->admin->roles()->attach($adminRole);

        // Grant roles.manage and roles.view to admin role so they can create roles
        $rolesManage = Permission::where('name', 'roles.manage')->firstOrFail();
        $rolesView = Permission::where('name', 'roles.view')->firstOrFail();
        $adminRole->permissions()->syncWithoutDetaching([$rolesManage->id, $rolesView->id]);
    }

    public function test_admin_cannot_assign_permission_outside_their_own_permissions(): void
    {
        $this->actingAs($this->admin);

        // Find a permission that admin DOES NOT have (e.g. system.manage or audit.view if restricted)
        $this->admin->loadMissing('roles.permissions');
        $adminPermissionIds = $this->admin->roles->flatMap->permissions->pluck('id')->all();

        $unauthorizedPermission = Permission::whereNotIn('id', $adminPermissionIds)->first();
        if (! $unauthorizedPermission) {
            // Create a specific restricted permission to test
            $unauthorizedPermission = Permission::create([
                'name' => 'restricted.special.permission',
                'description' => 'Permission not given to admin',
            ]);
        }

        Livewire::test(ManageRoles::class)
            ->callAction('create', data: [
                'name' => 'escalated_role',
                'description' => 'Attempting privilege escalation',
                'permissions' => [$unauthorizedPermission->id],
            ])
            ->assertHasActionErrors(['permissions']);

        $this->assertDatabaseMissing('roles', ['name' => 'escalated_role']);
    }

    public function test_admin_can_assign_permission_within_their_own_permissions(): void
    {
        $this->actingAs($this->admin);

        $this->admin->loadMissing('roles.permissions');
        $allowedPermission = $this->admin->roles->flatMap->permissions->firstOrFail();

        Livewire::test(ManageRoles::class)
            ->callAction('create', data: [
                'name' => 'allowed_custom_role',
                'description' => 'Role with permitted permission',
                'permissions' => [$allowedPermission->id],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('roles', ['name' => 'allowed_custom_role']);
    }

    public function test_super_admin_can_assign_any_permission(): void
    {
        $this->actingAs($this->superAdmin);

        $anyPermission = Permission::firstOrFail();

        Livewire::test(ManageRoles::class)
            ->callAction('create', data: [
                'name' => 'super_custom_role',
                'description' => 'Role created by super admin',
                'permissions' => [$anyPermission->id],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('roles', ['name' => 'super_custom_role']);
    }
}
