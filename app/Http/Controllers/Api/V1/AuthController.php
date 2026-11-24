<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate user and return an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with(['roles.permissions', 'organization'])
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Kredensial yang diberikan tidak cocok dengan data kami.', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Akun Anda dinonaktifkan. Silakan hubungi administrator.', 403);
        }

        $deviceName = $request->input('device_name', 'api-client');
        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = is_numeric($expirationMinutes) && (int) $expirationMinutes > 0
            ? now()->addMinutes((int) $expirationMinutes)
            : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toISOString(),
            'user' => new UserResource($user),
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->roles->flatMap->permissions->pluck('name')->unique()->values(),
        ], 'Login berhasil');
    }

    /**
     * Log the user out (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return $this->successResponse(null, 'Logout berhasil');
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'organization', 'farmerProfile']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->roles->flatMap->permissions->pluck('name')->unique()->values(),
            'is_farmer' => $user->farmerProfile !== null,
        ], 'Profil pengguna berhasil diambil');
    }
}
