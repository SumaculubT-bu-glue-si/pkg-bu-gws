<?php

namespace Bu\Gws\Providers;

use Illuminate\Support\ServiceProvider;

class GwsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/google-workspace.php' => config_path('google-workspace.php'),
            __DIR__ . '/../../config/lighthouse.php' => config_path('lighthouse.php'),
            __DIR__ . '/../../routes/api.php' => base_path('routes/api.php'),
            __DIR__ . '/../../graphql/schema.graphql' => base_path('graphql/schema.graphql'),
            __DIR__ . '/GoogleWorkspaceServiceProvider.php' => app_path('Providers/GoogleWorkspaceServiceProvider.php'),
            __DIR__ . '/AppServiceProvider.php' => app_path('Providers/AppServiceProvider.php'),
        ], 'gws-all');

        // Publish migrations
        if (is_dir(__DIR__ . '/../../database/migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }

        // Load routes if present
        if (file_exists(__DIR__ . '/../../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        }
        if (file_exists(__DIR__ . '/../../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }

        // Load GraphQL schema if present
        if (is_dir(__DIR__ . '/../../graphql')) {
            // You may need to instruct the host app to import these in their main schema
        }

        // Register package Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Bu\Gws\Console\Commands\SyncGoogleWorkspaceUsers::class,
                \Bu\Gws\Console\Commands\TestGoogleWorkspaceAuth::class,
            ]);
        }
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/google-workspace.php',
            'google-workspace'
        );
    }
}
