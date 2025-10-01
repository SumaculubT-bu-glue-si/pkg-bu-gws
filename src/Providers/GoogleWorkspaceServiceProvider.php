<?php

namespace App\Providers;

use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Support\ServiceProvider;

class GoogleWorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleWorkspaceService::class, function ($app) {
            return new GoogleWorkspaceService();
        });
    }

    public function boot(): void
    {
        //
    }
}
