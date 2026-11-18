<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'organization']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('role')) {
            $roleName = $request->role;
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->latest()->paginate($perPage);

        return $this->successResponse([
            'items' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 'Daftar pengguna berhasil dimuat');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']);
            $data['is_active'] = $data['is_active'] ?? true;

            $user = User::create($data);

            if ($request->filled('role_ids')) {
                $user->roles()->sync($request->role_ids);
            } elseif ($request->filled('role_names')) {
                $roleIds = Role::whereIn('name', $request->role_names)->pluck('id');
                $user->roles()->sync($roleIds);
            }

            return $user->load(['roles', 'organization']);
        });

        return $this->successResponse(
            new UserResource($user),
            'Pengguna berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified user.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with(['roles.permissions', 'organization', 'farmerProfile'])->find($id);

        if (! $user) {
            return $this->errorResponse('Pengguna tidak ditemukan', 404);
        }

        return $this->successResponse(new UserResource($user), 'Detail pengguna berhasil diambil');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->errorResponse('Pengguna tidak ditemukan', 404);
        }

        $updatedUser = DB::transaction(function () use ($request, $user) {
            $data = $request->validated();

            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if ($request->has('role_ids')) {
                $user->roles()->sync($request->role_ids ?? []);
            } elseif ($request->has('role_names')) {
                $roleIds = Role::whereIn('name', $request->role_names)->pluck('id');
                $user->roles()->sync($roleIds);
            }

            return $user->fresh(['roles', 'organization']);
        });

        return $this->successResponse(new UserResource($updatedUser), 'Pengguna berhasil diperbarui');
    }

    /**
     * Deactivate or remove the specified user.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->errorResponse('Pengguna tidak ditemukan', 404);
        }

        // Soft deactivation to preserve historical records
        $user->update(['is_active' => false]);

        return $this->successResponse(null, 'Pengguna berhasil dinonaktifkan');
    }
}
