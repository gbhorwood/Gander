<?php
namespace Gbhorwood\Gander;

use Illuminate\Support\ServiceProvider;

use Gbhorwood\Gander\Gander;
use Gbhorwood\Gander\GanderConsole;

class GanderServiceProvider extends ServiceProvider
{
    /**
     * Register method
     *
     */
    public function register()
    {
    }

    /**
     * Boot method
     *
     */
    public function boot()
    {
        /**
         * Migrations for gander_requests and gander_stack
         */
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        /**
         * Configuration file gander.php
         */
        $this->publishes([__DIR__.'/../config/gander.php' => config_path('gander.php')], 'config');

        /**
         * Routes for api for frontend
         */
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        /**
         * Push the middleware into the 'api' group
         */
        $router = $this->app['router'];
        $this->app->booted(function () use ($router) {
            $router->pushMiddlewareToGroup('api', \Gbhorwood\Gander\Gander::class);
        });

        /**
         * Handle artisan console script run
         */
        if ($this->app->runningInConsole()) {
        $this->commands([
            GanderConsole::class,
        ]);
    }
    }
}