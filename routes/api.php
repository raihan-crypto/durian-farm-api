<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\RbacController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public authentication endpoint
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected API endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // Authenticated user session
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Roles & Permissions
        Route::get('/roles', [RbacController::class, 'roles'])->middleware('permission:roles.view');
        Route::get('/permissions', [RbacController::class, 'permissions'])->middleware('permission:roles.view');

        // Organization management
        Route::get('/organizations', [OrganizationController::class, 'index'])->middleware('permission:organizations.view');
        Route::post('/organizations', [OrganizationController::class, 'store'])->middleware('permission:organizations.manage');
        Route::get('/organizations/{id}', [OrganizationController::class, 'show'])->middleware('permission:organizations.view');
        Route::put('/organizations/{id}', [OrganizationController::class, 'update'])->middleware('permission:organizations.manage');
        Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy'])->middleware('permission:organizations.manage');

        // User management
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
    });
});
