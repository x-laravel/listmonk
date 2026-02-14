<?php

namespace XLaravel\Listmonk;

use Illuminate\Support\ServiceProvider;
use XLaravel\Listmonk\Http\Client;

class ListmonkServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/listmonk.php', 'listmonk');

        $this->app->singleton(Client::class, function ($app) {
            return new Client(
                config('listmonk.base_url'),
                config('listmonk.api_user'),
                config('listmonk.api_token'),
            );
        });

        $this->app->singleton('listmonk', function ($app) {
            return new Listmonk($app);
        });

        $this->app->alias(Listmonk::class, 'listmonk');
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishing();
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $publishesMigrationsMethod = method_exists($this, 'publishesMigrations')
                ? 'publishesMigrations'
                : 'publishes';

            $this->{$publishesMigrationsMethod}([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'listmonk-migrations');


            $this->publishes([
                __DIR__ . '/../config/listmonk.php' => config_path('listmonk.php'),
            ], 'listmonk-config');
        }
    }
}