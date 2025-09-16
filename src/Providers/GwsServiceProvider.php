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
        ], 'config');

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
                // Add each command class here
                \Bu\Gws\Console\Commands\SendCorrectiveActionReminders::class,
                // Add other commands as needed, e.g.:
                // \Bu\Gws\Console\Commands\GwsSyncUserCommand::class,
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
