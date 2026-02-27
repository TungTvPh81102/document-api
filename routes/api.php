<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SystemConsole\UserController;
use App\Http\Controllers\Api\SystemConsole\RoleController;
use App\Http\Controllers\Api\SystemConsole\PermissionController;
use App\Http\Controllers\Api\SystemConsole\SiteController;
use App\Http\Controllers\Api\SystemConsole\PlantController;
use App\Http\Controllers\Api\SystemConsole\SubSiteController;
use App\Http\Controllers\Api\SystemConsole\ServiceController;
use App\Http\Controllers\Api\SystemConsole\ServiceRequestController;

Route::get('/health', [HealthController::class, 'health'])->name('health');

Route::prefix('/auth')->name('auth.')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\Auth\LoginController::class, 'login'])->name('login');
    Route::post('/logout', [\App\Http\Controllers\Api\Auth\LoginController::class, 'logout'])->name('logout');
});

// Dynamic Service Module
Route::get('services/listing', [ServiceController::class, 'index']);
Route::get('services/{svcCode}/form', [ServiceController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    // User routes (fixed from previous broken state)
    Route::get('/user', [UserController::class, 'me'])->name('users.me');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/search', [UserController::class, 'search'])->name('search');
        Route::get('/stats', [UserController::class, 'stats'])->name('stats');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{code}', [UserController::class, 'show'])->name('show');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
        Route::delete('/{id}/force', [UserController::class, 'forceDelete'])->name('force');
        Route::post('/{id}/restore', [UserController::class, 'restore'])->name('restore');
        Route::post('/{id}/enable', [UserController::class, 'enable'])->name('enable');
        Route::post('/{id}/disable', [UserController::class, 'disable'])->name('disable');
        Route::post('/{id}/lock', [UserController::class, 'lock'])->name('lock');
        Route::post('/{id}/unlock', [UserController::class, 'unlock'])->name('unlock');
        Route::post('/{id}/roles', [UserController::class, 'syncRoles'])->name('sync-roles');
        Route::post('/bulk-delete', [UserController::class, 'bulkDelete'])->name('bulk-delete');
    });

    // Service Requests
    Route::post('service-requests', [ServiceRequestController::class, 'store']);
    Route::get('service-requests/{requestNo}', [ServiceRequestController::class, 'show']);

    // Admin Service Management
    Route::prefix('admin')->group(function () {
        Route::post('services/ingest', [\App\Http\Controllers\Api\SystemConsole\ServiceManagementController::class, 'ingest']);
    });
});

Route::prefix('roles')->name('roles.')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::get('/{id}', [RoleController::class, 'show'])->name('show');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::put('/{id}', [RoleController::class, 'update'])->name('update');
    Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/permissions', [RoleController::class, 'syncPermissions'])->name('sync-permissions');
});

Route::prefix('permissions')->name('permissions.')->group(function () {
    Route::get('/', [PermissionController::class, 'index'])->name('index');
    Route::get('/{id}', [PermissionController::class, 'show'])->name('show');
    Route::post('/', [PermissionController::class, 'store'])->name('store');
    Route::put('/{id}', [PermissionController::class, 'update'])->name('update');
    Route::delete('/{id}', [PermissionController::class, 'delete'])->name('delete');
});

Route::prefix('sites')->name('sites.')->group(function () {
    Route::get('/', [SiteController::class, 'index'])->name('index');
    Route::get('/{id}', [SiteController::class, 'show'])->name('show');
    Route::post('/', [SiteController::class, 'store'])->name('store');
    Route::put('/{id}', [SiteController::class, 'update'])->name('update');
    Route::delete('/{id}', [SiteController::class, 'destroy'])->name('destroy');
});

Route::prefix('plants')->name('plants.')->group(function () {
    Route::get('/', [PlantController::class, 'index'])->name('index');
    Route::get('/{id}', [PlantController::class, 'show'])->name('show');
    Route::post('/', [PlantController::class, 'store'])->name('store');
    Route::put('/{id}', [PlantController::class, 'update'])->name('update');
    Route::delete('/{id}', [PlantController::class, 'delete'])->name('delete');
});

Route::prefix('sub-sites')->name('sub-sites.')->group(function () {
    Route::get('/', [SubSiteController::class, 'index'])->name('index');
    Route::get('/{id}', [SubSiteController::class, 'show'])->name('show');
    Route::post('/', [SubSiteController::class, 'store'])->name('store');
    Route::put('/{id}', [SubSiteController::class, 'update'])->name('update');
    Route::delete('/{id}', [SubSiteController::class, 'delete'])->name('delete');
});
// Media Routes (Polymorphic - supports User, Post, Product, etc)
Route::middleware('auth:sanctum')->prefix('v1/media')->name('media.')->group(function () {
    // Upload media file
    // POST /api/v1/media/{modelType}/{modelId}/{collection}
    Route::post(
        '/{modelType}/{modelId}/{collection}',
        [\App\Http\Controllers\Api\V1\MediaController::class, 'upload']
    )->name('upload');

    // List media of a model
    // GET /api/v1/media/{modelType}/{modelId}
    Route::get(
        '/{modelType}/{modelId}',
        [\App\Http\Controllers\Api\V1\MediaController::class, 'index']
    )->name('index');

    // Delete media
    // DELETE /api/v1/media/{mediaId}
    Route::delete(
        '/{mediaId}',
        [\App\Http\Controllers\Api\V1\MediaController::class, 'destroy']
    )->name('destroy');
});
