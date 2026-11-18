<?php

use App\Http\Controllers\Api\V1\ActivityPhotoController;
use App\Http\Controllers\Api\V1\AgriculturalProductController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\DurianVarietyController;
use App\Http\Controllers\Api\V1\FarmerController;
use App\Http\Controllers\Api\V1\FertilizationRecordController;
use App\Http\Controllers\Api\V1\FloweringRecordController;
use App\Http\Controllers\Api\V1\FruitDevelopmentRecordController;
use App\Http\Controllers\Api\V1\GardenController;
use App\Http\Controllers\Api\V1\HarvestRecordController;
use App\Http\Controllers\Api\V1\HarvestReportController;
use App\Http\Controllers\Api\V1\HarvestSeasonController;
use App\Http\Controllers\Api\V1\HarvestSessionController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OwnerController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\PublicTreeController;
use App\Http\Controllers\Api\V1\RbacController;
use App\Http\Controllers\Api\V1\SprayingRecordController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TreeController;
use App\Http\Controllers\Api\V1\TreeMeasurementController;
use App\Http\Controllers\Api\V1\TreePhotoController;
use App\Http\Controllers\Api\V1\TreeQrCodeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VillageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public authentication endpoint
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public tree information via QR Code token (rate-limited, SRS 10 & 16.3)
    Route::get('/public/trees/{token}', [PublicTreeController::class, 'show'])
        ->middleware('throttle:public-tree');

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

        // Durian varieties master data
        Route::get('/durian-varieties', [DurianVarietyController::class, 'index'])->middleware('permission:trees.view');
        Route::post('/durian-varieties', [DurianVarietyController::class, 'store'])->middleware('permission:trees.manage');
        Route::get('/durian-varieties/{id}', [DurianVarietyController::class, 'show'])->middleware('permission:trees.view');
        Route::put('/durian-varieties/{id}', [DurianVarietyController::class, 'update'])->middleware('permission:trees.manage');
        Route::delete('/durian-varieties/{id}', [DurianVarietyController::class, 'destroy'])->middleware('permission:trees.manage');
        Route::post('/durian-varieties/{id}/photo', [DurianVarietyController::class, 'uploadPhoto'])->middleware('permission:trees.manage');
        Route::delete('/durian-varieties/{id}/photo', [DurianVarietyController::class, 'deletePhoto'])->middleware('permission:trees.manage');

        // Durian trees, history, photos & QR Code management
        Route::get('/trees/statuses', [TreeController::class, 'statuses'])->middleware('permission:trees.view');
        Route::get('/trees/qr-public-fields', [TreeQrCodeController::class, 'publicFieldsMeta'])->middleware('permission:trees.view');
        Route::get('/trees', [TreeController::class, 'index'])->middleware('permission:trees.view');
        Route::post('/trees', [TreeController::class, 'store'])->middleware('permission:trees.manage');
        Route::get('/trees/{id}', [TreeController::class, 'show'])->middleware('permission:trees.view');
        Route::put('/trees/{id}', [TreeController::class, 'update'])->middleware('permission:trees.manage');
        Route::delete('/trees/{id}', [TreeController::class, 'destroy'])->middleware('permission:trees.manage');
        Route::get('/trees/{id}/history', [TreeController::class, 'history'])->middleware('permission:trees.view');

        // Tree measurements (historical)
        Route::get('/trees/{id}/measurements', [TreeMeasurementController::class, 'indexByTree'])->middleware('permission:trees.view');
        Route::post('/tree-measurements', [TreeMeasurementController::class, 'store'])->middleware('permission:trees.measure');
        Route::get('/tree-measurements/{id}', [TreeMeasurementController::class, 'show'])->middleware('permission:trees.view');

        // Tree photos
        Route::get('/trees/{id}/photos', [TreePhotoController::class, 'index'])->middleware('permission:trees.view');
        Route::post('/trees/{id}/photos', [TreePhotoController::class, 'store'])->middleware('permission:trees.manage,trees.measure');
        Route::delete('/trees/{id}/photos/{photoId}', [TreePhotoController::class, 'destroy'])->middleware('permission:trees.manage');

        // Tree QR Code
        Route::get('/trees/{id}/qr-code', [TreeQrCodeController::class, 'show'])->middleware('permission:trees.view');
        Route::put('/trees/{id}/qr-code', [TreeQrCodeController::class, 'update'])->middleware('permission:trees.manage');
        Route::post('/trees/{id}/qr-code/regenerate', [TreeQrCodeController::class, 'regenerate'])->middleware('permission:trees.manage');
        Route::get('/trees/{id}/qr-code/image', [TreeQrCodeController::class, 'image'])->middleware('permission:trees.view');

        // Product categories master data
        Route::get('/product-categories', [ProductCategoryController::class, 'index'])->middleware('permission:products.view');
        Route::post('/product-categories', [ProductCategoryController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/product-categories/{id}', [ProductCategoryController::class, 'show'])->middleware('permission:products.view');
        Route::put('/product-categories/{id}', [ProductCategoryController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/product-categories/{id}', [ProductCategoryController::class, 'destroy'])->middleware('permission:products.manage');

        // Agricultural products master data
        Route::get('/agricultural-products', [AgriculturalProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('/agricultural-products', [AgriculturalProductController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/agricultural-products/{id}', [AgriculturalProductController::class, 'show'])->middleware('permission:products.view');
        Route::put('/agricultural-products/{id}', [AgriculturalProductController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/agricultural-products/{id}', [AgriculturalProductController::class, 'destroy'])->middleware('permission:products.manage');

        // Plantation activities: Fertilization
        Route::get('/fertilization-records/methods', [FertilizationRecordController::class, 'methods'])->middleware('permission:activities.view');
        Route::get('/fertilization-records', [FertilizationRecordController::class, 'index'])->middleware('permission:activities.view');
        Route::post('/fertilization-records', [FertilizationRecordController::class, 'store'])->middleware('permission:activities.record');
        Route::get('/fertilization-records/{id}', [FertilizationRecordController::class, 'show'])->middleware('permission:activities.view');

        // Plantation activities: Spraying
        Route::get('/spraying-records', [SprayingRecordController::class, 'index'])->middleware('permission:activities.view');
        Route::post('/spraying-records', [SprayingRecordController::class, 'store'])->middleware('permission:activities.record');
        Route::get('/spraying-records/{id}', [SprayingRecordController::class, 'show'])->middleware('permission:activities.view');

        // Plantation activities: Flowering
        Route::get('/flowering-records/statuses', [FloweringRecordController::class, 'statuses'])->middleware('permission:activities.view');
        Route::get('/flowering-records', [FloweringRecordController::class, 'index'])->middleware('permission:activities.view');
        Route::post('/flowering-records', [FloweringRecordController::class, 'store'])->middleware('permission:activities.record');
        Route::get('/flowering-records/{id}', [FloweringRecordController::class, 'show'])->middleware('permission:activities.view');

        // Plantation activities: Fruit development
        Route::get('/fruit-development-records/stages', [FruitDevelopmentRecordController::class, 'stages'])->middleware('permission:activities.view');
        Route::get('/fruit-development-records', [FruitDevelopmentRecordController::class, 'index'])->middleware('permission:activities.view');
        Route::post('/fruit-development-records', [FruitDevelopmentRecordController::class, 'store'])->middleware('permission:activities.record');
        Route::get('/fruit-development-records/{id}', [FruitDevelopmentRecordController::class, 'show'])->middleware('permission:activities.view');

        // Centralized polymorphic activity photos
        Route::get('/activity-photos', [ActivityPhotoController::class, 'index'])->middleware('permission:activities.view');
        Route::post('/activity-photos', [ActivityPhotoController::class, 'store'])->middleware('permission:activities.record,trees.manage,trees.measure');
        Route::get('/activity-photos/{id}', [ActivityPhotoController::class, 'show'])->middleware('permission:activities.view');
        Route::delete('/activity-photos/{id}', [ActivityPhotoController::class, 'destroy'])->middleware('permission:activities.record,trees.manage');

        // Harvest seasons
        Route::get('/harvest-seasons', [HarvestSeasonController::class, 'index'])->middleware('permission:harvest.view');
        Route::post('/harvest-seasons', [HarvestSeasonController::class, 'store'])->middleware('permission:harvest.manage');
        Route::get('/harvest-seasons/{id}', [HarvestSeasonController::class, 'show'])->middleware('permission:harvest.view');
        Route::put('/harvest-seasons/{id}', [HarvestSeasonController::class, 'update'])->middleware('permission:harvest.manage');
        Route::delete('/harvest-seasons/{id}', [HarvestSeasonController::class, 'destroy'])->middleware('permission:harvest.manage');

        // Harvest sessions
        Route::get('/harvest-sessions', [HarvestSessionController::class, 'index'])->middleware('permission:harvest.view');
        Route::post('/harvest-sessions', [HarvestSessionController::class, 'store'])->middleware('permission:harvest.manage,harvest.record');
        Route::get('/harvest-sessions/{id}', [HarvestSessionController::class, 'show'])->middleware('permission:harvest.view');
        Route::put('/harvest-sessions/{id}', [HarvestSessionController::class, 'update'])->middleware('permission:harvest.manage');
        Route::delete('/harvest-sessions/{id}', [HarvestSessionController::class, 'destroy'])->middleware('permission:harvest.manage');

        // Per-tree harvest records & grade details
        Route::get('/harvest-records', [HarvestRecordController::class, 'index'])->middleware('permission:harvest.view');
        Route::post('/harvest-records', [HarvestRecordController::class, 'store'])->middleware('permission:harvest.record');
        Route::get('/harvest-records/{id}', [HarvestRecordController::class, 'show'])->middleware('permission:harvest.view');
        Route::put('/harvest-records/{id}', [HarvestRecordController::class, 'update'])->middleware('permission:harvest.manage,harvest.record');

        // Harvest productivity reports & export
        Route::get('/reports/harvest/summary', [HarvestReportController::class, 'summary'])->middleware('permission:reports.view,harvest.view');
        Route::get('/reports/harvest/comparison', [HarvestReportController::class, 'comparison'])->middleware('permission:reports.view,harvest.view');
        Route::get('/reports/harvest/export', [HarvestReportController::class, 'export'])->middleware('permission:reports.export');

        // Offline-first mobile sync engine (SRS Bab 12 & 16.3)
        Route::get('/sync/bootstrap', [SyncController::class, 'bootstrap'])->middleware('permission:sync.access');
        Route::get('/sync/changes', [SyncController::class, 'changes'])->middleware('permission:sync.access');
        Route::post('/sync/pull', [SyncController::class, 'changes'])->middleware('permission:sync.access');
        Route::post('/sync/push', [SyncController::class, 'push'])->middleware('permission:sync.access');
        Route::get('/sync/logs', [SyncController::class, 'logs'])->middleware('permission:sync.access,audit.view');
    });
});
