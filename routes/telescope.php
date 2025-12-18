<?php

use Illuminate\Support\Facades\Route;
use Laravel\Telescope\Http\Middleware\Authorize;

Route::domain(config('telescope.domain', null))
    ->prefix(config('telescope.path'))
    ->middleware(['web', Authorize::class])
    ->group(function () {
        Route::get('/{view?}', [\Laravel\Telescope\Http\Controllers\HomeController::class, 'index'])
            ->where('view', '(.*)')
            ->name('telescope');
    });