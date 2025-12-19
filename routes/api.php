<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\UserController;

Route::get('/health', [HealthController::class, 'health'])->name('health');

// Route::middleware(['auth:sanctum', 'is.user.active'])->group(function () {
// Current User
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

    Route::post('/bulk-delete', [UserController::class, 'bulkDelete'])->name('bulk-delete');
});

// });
