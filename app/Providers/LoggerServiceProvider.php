<?php

namespace App\Providers;

use App\Services\ActionLoggerService;
use App\Services\OperationLoggerService;
use App\Services\LoggerService;
use Illuminate\Support\ServiceProvider;

/**
 * Logger Service Provider
 * 
 * Register các logging services vào container
 */
class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register LoggerService
        $this->app->singleton(LoggerService::class, function ($app) {
            return new LoggerService();
        });

        // Register ActionLoggerService
        $this->app->singleton(ActionLoggerService::class, function ($app) {
            return new ActionLoggerService();
        });

        // Register OperationLoggerService
        $this->app->singleton(OperationLoggerService::class, function ($app) {
            return new OperationLoggerService();
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/logging-enhanced.php' => config_path('logging-enhanced.php'),
        ], 'config');
    }
}
