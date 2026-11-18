<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\FarmerController;
use App\Http\Controllers\Api\V1\GardenController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OwnerController;
use App\Http\Controllers\Api\V1\RbacController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VillageController;
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

        // Authenticated farmer assignments
        Route::get('/my-assignments', [FarmerController::class, 'myAssignments']);

        // Villages management
        Route::get('/villages', [VillageController::class, 'index'])->middleware('permission:territory.view');
        Route::post('/villages', [VillageController::class, 'store'])->middleware('permission:territory.manage');
        Route::get('/villages/{id}', [VillageController::class, 'show'])->middleware('permission:territory.view');
        Route::put('/villages/{id}', [VillageController::class, 'update'])->middleware('permission:territory.manage');
        Route::delete('/villages/{id}', [VillageController::class, 'destroy'])->middleware('permission:territory.manage');

        // Blocks management & block farmer assignments
        Route::get('/blocks', [BlockController::class, 'index'])->middleware('permission:territory.view');
        Route::post('/blocks', [BlockController::class, 'store'])->middleware('permission:territory.manage');
        Route::get('/blocks/{id}', [BlockController::class, 'show'])->middleware('permission:territory.view');
        Route::put('/blocks/{id}', [BlockController::class, 'update'])->middleware('permission:territory.manage');
        Route::delete('/blocks/{id}', [BlockController::class, 'destroy'])->middleware('permission:territory.manage');
        Route::get('/blocks/{id}/farmers', [BlockController::class, 'farmers'])->middleware('permission:territory.view');
        Route::post('/blocks/{id}/farmers', [BlockController::class, 'assignFarmer'])->middleware('permission:ownership.manage');
        Route::put('/blocks/{id}/farmers/{assignmentId}', [BlockController::class, 'updateFarmerAssignment'])->middleware('permission:ownership.manage');
        Route::delete('/blocks/{id}/farmers/{assignmentId}', [BlockController::class, 'removeFarmerAssignment'])->middleware('permission:ownership.manage');

        // Gardens management, multi-ownership & garden farmer assignments
        Route::get('/gardens', [GardenController::class, 'index'])->middleware('permission:territory.view');
        Route::post('/gardens', [GardenController::class, 'store'])->middleware('permission:territory.manage');
        Route::get('/gardens/{id}', [GardenController::class, 'show'])->middleware('permission:territory.view');
        Route::put('/gardens/{id}', [GardenController::class, 'update'])->middleware('permission:territory.manage');
        Route::delete('/gardens/{id}', [GardenController::class, 'destroy'])->middleware('permission:territory.manage');
        Route::get('/gardens/{id}/owners', [GardenController::class, 'owners'])->middleware('permission:territory.view');
        Route::post('/gardens/{id}/owners', [GardenController::class, 'assignOwner'])->middleware('permission:ownership.manage');
        Route::put('/gardens/{id}/owners/{ownershipId}', [GardenController::class, 'updateOwner'])->middleware('permission:ownership.manage');
        Route::delete('/gardens/{id}/owners/{ownershipId}', [GardenController::class, 'removeOwner'])->middleware('permission:ownership.manage');
        Route::get('/gardens/{id}/farmers', [GardenController::class, 'farmers'])->middleware('permission:territory.view');
        Route::post('/gardens/{id}/farmers', [GardenController::class, 'assignFarmer'])->middleware('permission:ownership.manage');
        Route::put('/gardens/{id}/farmers/{assignmentId}', [GardenController::class, 'updateFarmerAssignment'])->middleware('permission:ownership.manage');
        Route::delete('/gardens/{id}/farmers/{assignmentId}', [GardenController::class, 'removeFarmerAssignment'])->middleware('permission:ownership.manage');

        // Owners management
        Route::get('/owners', [OwnerController::class, 'index'])->middleware('permission:ownership.view');
        Route::post('/owners', [OwnerController::class, 'store'])->middleware('permission:ownership.manage');
        Route::get('/owners/{id}', [OwnerController::class, 'show'])->middleware('permission:ownership.view');
        Route::put('/owners/{id}', [OwnerController::class, 'update'])->middleware('permission:ownership.manage');
        Route::delete('/owners/{id}', [OwnerController::class, 'destroy'])->middleware('permission:ownership.manage');

        // Farmers management
        Route::get('/farmers', [FarmerController::class, 'index'])->middleware('permission:ownership.view');
        Route::post('/farmers', [FarmerController::class, 'store'])->middleware('permission:ownership.manage');
        Route::get('/farmers/{id}', [FarmerController::class, 'show'])->middleware('permission:ownership.view');
        Route::put('/farmers/{id}', [FarmerController::class, 'update'])->middleware('permission:ownership.manage');
        Route::delete('/farmers/{id}', [FarmerController::class, 'destroy'])->middleware('permission:ownership.manage');
    });
});
