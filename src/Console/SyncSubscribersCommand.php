<?php

namespace XLaravel\Listmonk\Console;

use Illuminate\Console\Command;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;

class SyncSubscribersCommand extends Command
{
    protected $signature = 'listmonk:sync 
                            {model? : The model class to sync (defaults to User model)}
                            {--chunk=100 : Number of records to process at once}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Sync subscribers to Listmonk';

    public function handle(): int
    {
        $modelClass = $this->argument('model') ?: $this->getDefaultModel();
        $chunk = (int) $this->option('chunk');

        if (!class_exists($modelClass)) {
            $this->components->error("Model class [{$modelClass}] does not exist.");
            return self::FAILURE;
        }

        if (!in_array(NewsletterSubscriber::class, class_implements($modelClass))) {
            $this->components->error("Model [{$modelClass}] must implement NewsletterSubscriber interface.");
            return self::FAILURE;
        }

        $total = $modelClass::count();

        $this->components->info("Found {$total} records to sync.");

        if (!$this->option('force') && !$this->confirm('Do you want to continue?', true)) {
            $this->components->warn('Sync cancelled.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Starting sync...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $failed = 0;
        $errors = [];

        $modelClass::chunk($chunk, function ($models) use ($bar, &$synced, &$failed, &$errors) {
            foreach ($models as $model) {
                try {
                    $model->subscribeToNewsletter();
                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'model' => get_class($model),
                        'id' => $model->id ?? 'unknown',
                        'email' => method_exists($model, 'getNewsletterEmail')
                            ? $model->getNewsletterEmail()
                            : 'N/A',
                        'error' => $e->getMessage()
                    ];
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->components->info("Sync completed!");
        $this->components->twoColumnDetail('Total', $total);
        $this->components->twoColumnDetail('Synced', "<fg=green>{$synced}</>");

        if ($failed > 0) {
            $this->components->twoColumnDetail('Failed', "<fg=red>{$failed}</>");

            $this->newLine();
            $this->components->warn('Failed records:');

            foreach (array_slice($errors, 0, 10) as $error) {
                $this->line("  - {$error['email']} (ID: {$error['id']}): {$error['error']}");
            }

            if (count($errors) > 10) {
                $this->line("  ... and " . (count($errors) - 10) . " more");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function getDefaultModel(): string
    {
        return config('auth.providers.users.model', 'App\\Models\\User');
    }
}
