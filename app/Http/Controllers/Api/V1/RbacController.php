<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class RbacController extends Controller
{
    use ApiResponse;

    /**
     * Get all roles with their permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return $this->successResponse(
            RoleResource::collection($roles),
            'Daftar role berhasil dimuat'
        );
    }

    /**
     * Get all available permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all();

        return $this->successResponse(
            PermissionResource::collection($permissions),
            'Daftar permission berhasil dimuat'
        );
    }
}
