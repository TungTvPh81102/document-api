<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\UserController;

Route::get('/health', [HealthController::class, 'health']);

// Route::middleware(['auth:sanctum', 'is.user.active'])->group(function () {
// Current User
Route::get('/user', [UserController::class, 'me']);

Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{code}', [UserController::class, 'show']);
});


// });
