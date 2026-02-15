<?php

namespace XLaravel\Listmonk;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use XLaravel\Listmonk\Services\Lists;
use XLaravel\Listmonk\Services\NewsletterManager;
use XLaravel\Listmonk\Services\Subscribers;

class ListmonkServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is responsible for binding all
     * package services into the container.
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerHttpClient();
        $this->registerSubscribersService();
        $this->registerListsService();
        $this->registerNewsletterManager();
        $this->registerMainBinding();
    }

    /**
     * Bootstrap any package services.
     *
     * This method runs after all services are registered.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerObserver();
    }

    /**
     * Merge package configuration with the application config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/listmonk.php',
            'listmonk'
        );
    }

    /**
     * Register the configured HTTP client used for
     * communicating with the Listmonk API.
     *
     * The client is pre-configured with:
     * - Authorization header
     * - Base URL
     * - Timeout
     * - Retry strategy
     */
    protected function registerHttpClient(): void
    {
        $this->app->singleton(PendingRequest::class, function () {
            $apiUser = config('listmonk.api_user');
            $apiToken = config('listmonk.api_token');

            if (empty($apiUser) || empty($apiToken)) {
                throw new \RuntimeException(
                    'Listmonk API credentials are not configured. ' .
                    'Please set LISTMONK_API_USER and LISTMONK_API_TOKEN in your .env file.'
                );
            }

            $authHeader = "token {$apiUser}:{$apiToken}";

            return Http::withHeaders([
                'Authorization' => $authHeader,
                'Accept' => 'application/json',
            ])
                ->baseUrl(config('listmonk.base_url'))
                ->timeout(10)
                ->retry(3, 200);
        });
    }

    /**
     * Register the Subscribers API wrapper service.
     */
    protected function registerSubscribersService(): void
    {
        $this->app->singleton(Subscribers::class, function ($app) {
            return new Subscribers(
                $app->make(PendingRequest::class)
            );
        });
    }

    /**
     * Register the Lists API wrapper service.
     */
    protected function registerListsService(): void
    {
        $this->app->singleton(Lists::class, function ($app) {
            return new Lists(
                $app->make(PendingRequest::class)
            );
        });
    }

    /**
     * Register the NewsletterManager service.
     */
    protected function registerNewsletterManager(): void
    {
        $this->app->singleton(NewsletterManager::class, function ($app) {
            return new NewsletterManager(
                $app->make(Subscribers::class)
            );
        });
    }

    /**
     * Register the main Listmonk facade binding.
     */
    protected function registerMainBinding(): void
    {
        $this->app->singleton('listmonk', function ($app) {
            return new Listmonk($app);
        });

        $this->app->alias(Listmonk::class, 'listmonk');
    }

    /**
     * Register publishable resources such as
     * migrations and configuration files.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/listmonk.php' => config_path('listmonk.php'),
            ], 'listmonk-config');
        }
    }

    /**
     * Register package console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \XLaravel\Listmonk\Console\ListmonkHealthCommand::class,
                \XLaravel\Listmonk\Console\SyncSubscribersCommand::class,
            ]);
        }
    }

    /**
     * Register the NewsletterSubscriber observer.
     *
     * This observer watches all models that use the InteractsWithNewsletter trait
     * and automatically syncs them with Listmonk on lifecycle events.
     */
    protected function registerObserver(): void
    {
        $declaredClasses = get_declared_classes();

        foreach ($declaredClasses as $class) {
            if (!is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            $traits = class_uses_recursive($class);

            if (in_array(\XLaravel\Listmonk\Traits\InteractsWithNewsletter::class, $traits)) {
                $class::observe(\XLaravel\Listmonk\Observers\NewsletterSubscriberObserver::class);
            }
        }
    }
}
