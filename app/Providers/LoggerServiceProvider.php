<?php

namespace App\Providers;

use App\Services\LoggerService;
use Illuminate\Support\ServiceProvider;

class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoggerService::class, function ($app) {
            return new LoggerService();
        });
    }

    public function boot(): void
    {
        //
    }
}
