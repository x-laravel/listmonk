<?php

namespace XLaravel\Listmonk\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use XLaravel\Listmonk\Contracts\HasSubscriber;
use XLaravel\Listmonk\Jobs\SyncSubscriber;
use XLaravel\Listmonk\Support\ListmonkConfig;

class SyncCommand extends Command
{
    protected $signature = 'listmonk:sync
                            {model* : Models to sync}
                            {--chunk=1000 : Models read by each query}';

    protected $description = 'Queue a Listmonk sync for every record of the given models';

    public function handle(): int
    {
        if (! ListmonkConfig::enabled()) {
            $this->components->error('Listmonk is not enabled.');

            return self::FAILURE;
        }

        /** @var list<string> $models */
        $models = $this->argument('model');

        foreach ($models as $model) {
            if (! is_subclass_of($model, Model::class) || ! is_subclass_of($model, HasSubscriber::class)) {
                $this->components->error("[{$model}] is not a subscribable model.");

                return self::FAILURE;
            }
        }

        foreach ($models as $model) {
            $queued = 0;

            $model::query()->chunkById(max(1, (int) $this->option('chunk')), function ($records) use (&$queued) {
                foreach ($records as $record) {
                    $key = $record->getKey();

                    if (is_int($key) || is_string($key)) {
                        Bus::dispatch(new SyncSubscriber($record->getMorphClass(), $key));
                        $queued++;
                    }
                }
            });

            $this->components->info("Queued {$queued} [{$model}] records.");
        }

        return self::SUCCESS;
    }
}
