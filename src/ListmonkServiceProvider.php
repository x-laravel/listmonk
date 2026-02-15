<?php

namespace XLaravel\Listmonk;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use XLaravel\Listmonk\Services\Subscribers;

class ListmonkServiceProvider extends ServiceProvider implements DeferrableProvider
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

        // Validation sadece console'da değilse çalışsın
        // Console'da zaten command ilk çalıştığında hata verecek
        if (!$this->app->runningInConsole()) {
            $this->validateConfiguration();
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * Web request'lerinde deferred olarak yüklenir.
     * Console'da normal yüklenir (commands için).
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        // Console'da deferred olma, commands register olsun
        if ($this->app->runningInConsole()) {
            return [];
        }

        // Web request'lerinde sadece gerektiğinde yükle
        return [
            'listmonk',
            Listmonk::class,
            Subscribers::class,
            PendingRequest::class,
        ];
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
     * Register the Subscribers service.
     *
     * This service handles all subscriber synchronization
     * logic between Laravel models and Listmonk.
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
        // Get all loaded classes
        $declaredClasses = get_declared_classes();

        foreach ($declaredClasses as $class) {
            // Skip non-model classes
            if (!is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            // Check if model uses InteractsWithNewsletter trait
            $traits = class_uses_recursive($class);

            if (in_array(\XLaravel\Listmonk\Traits\InteractsWithNewsletter::class, $traits)) {
                $class::observe(\XLaravel\Listmonk\Observers\NewsletterSubscriberObserver::class);
            }
        }
    }

    /**
     * Validate that required configuration is present.
     */
    protected function validateConfiguration(): void
    {
        // Base URL validation
        $baseUrl = config('listmonk.base_url');
        if (empty($baseUrl)) {
            throw new \RuntimeException(
                'Listmonk base URL is not configured. ' .
                'Please set LISTMONK_BASE_URL in your .env file.'
            );
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException(
                "Invalid Listmonk base URL format: {$baseUrl}"
            );
        }

        // Preconfirm validation
        $preconfirm = config('listmonk.preconfirm_subscriptions');
        if (!is_bool($preconfirm)) {
            throw new \RuntimeException(
                'LISTMONK_PRECONFIRM_SUBSCRIPTIONS must be true or false (boolean).'
            );
        }

        // Default lists validation
        $defaultLists = config('listmonk.default_lists', []);
        if (!is_array($defaultLists)) {
            throw new \RuntimeException(
                'listmonk.default_lists must be an array.'
            );
        }

        // Queue configuration validation
        $queueEnabled = config('listmonk.queue.enabled');
        if (!is_bool($queueEnabled)) {
            throw new \RuntimeException(
                'LISTMONK_QUEUE_ENABLED must be true or false (boolean).'
            );
        }

        $tries = config('listmonk.queue.tries', 3);
        if (!is_numeric($tries) || $tries < 1) {
            throw new \RuntimeException(
                'LISTMONK_QUEUE_TRIES must be a positive integer.'
            );
        }
    }
}
