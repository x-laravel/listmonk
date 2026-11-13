<?php

namespace XLaravel\Listmonk;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use XLaravel\Listmonk\Console\HealthCommand;
use XLaravel\Listmonk\Console\SyncCommand;
use XLaravel\Listmonk\Support\ListmonkConfig;

class ListmonkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/listmonk.php', 'listmonk');
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, $this->schedulePruning(...));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([HealthCommand::class, SyncCommand::class]);

        $this->publishes([
            __DIR__ . '/../config/listmonk.php' => $this->app->configPath('listmonk.php'),
        ], 'listmonk-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'listmonk-migrations');
    }

    private function schedulePruning(Schedule $schedule): void
    {
        if (! Config::boolean('listmonk.prune.enabled', true) || ! ListmonkConfig::enabled()) {
            return;
        }

        $frequency = Config::string('listmonk.prune.schedule', 'daily');

        $event = $schedule->command('model:prune', [
            '--model' => [ListmonkConfig::subscriberModel()],
            '--chunk' => Config::integer('listmonk.prune.chunk', 1000),
        ]);

        if (str_contains($frequency, ' ')) {
            $event->cron($frequency);

            return;
        }

        [$method, $arguments] = array_pad(explode(':', $frequency, 2), 2, '');

        if ($method === '' || ! method_exists($event, $method)) {
            throw new InvalidArgumentException("[{$frequency}] is not a schedule frequency.");
        }

        $event->{$method}(...($arguments === '' ? [] : explode(',', $arguments)));
    }
}
