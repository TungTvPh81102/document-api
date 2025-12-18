<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Providers\TelescopeServiceProvider as AppTelescopeServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);          
            $this->app->register(AppTelescopeServiceProvider::class);       
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::addNamespace('telescope', base_path('vendor/laravel/telescope/resources/views'));
    }
}
