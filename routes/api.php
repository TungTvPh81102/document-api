<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\UserController;

Route::get('/health', [HealthController::class, 'health']);

// Route::middleware(['auth:sanctum', 'is.user.active'])->group(function () {
// Current User
Route::get('/user', [UserController::class, 'me']);

Route::get('/users', [UserController::class, 'index']);

    // Route::group(function () {
    //     Route::get('/users', [UserController::class, 'index']);
    //     Route::post('/users', [UserController::class, 'store']);
    //     Route::get('/users/statistics', [UserController::class, 'statistics']);
    //     Route::get('/users/{user}', [UserController::class, 'show']);
    //     Route::put('/users/{user}', [UserController::class, 'update']);
    //     Route::delete('/users/{user}', [UserController::class, 'destroy']);
    //     Route::post('/users/{user}/lock', [UserController::class, 'lock']);
    //     Route::post('/users/{user}/unlock', [UserController::class, 'unlock']);
    //     Route::post('/users/{user}/enable', [UserController::class, 'enable']);
    //     Route::post('/users/{user}/disable', [UserController::class, 'disable']);
    // });

// });
