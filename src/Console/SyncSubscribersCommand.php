<?php

namespace XLaravel\Listmonk\Console;

use Illuminate\Console\Command;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;

class SyncSubscribersCommand extends Command
{
    protected $signature = 'listmonk:sync 
                            {model? : The model class to sync (defaults to User model)}
                            {--chunk=100 : Number of records to process at once}
                            {--force : Skip confirmation prompt}
                            {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync subscribers to Listmonk';

    public function handle(): int
    {
        $modelClass = $this->argument('model') ?: $this->getDefaultModel();
        $chunk = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

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

        if ($dryRun) {
            $this->components->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        if (!$this->option('force') && !$this->confirm('Do you want to continue?', true)) {
            $this->components->warn('Sync cancelled.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info($dryRun ? 'Simulating sync...' : 'Starting sync...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $failed = 0;
        $errors = [];

        $modelClass::chunk($chunk, function ($models) use ($bar, &$synced, &$failed, &$errors, $dryRun) {
            foreach ($models as $model) {
                if ($dryRun) {
                    // Dry run - just show what would happen
                    $this->showDryRunInfo($model);
                    $synced++;
                } else {
                    // Actual sync
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
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->components->info($dryRun ? "Dry run completed!" : "Sync completed!");
        $this->components->twoColumnDetail('Total', $total);

        if ($dryRun) {
            $this->components->twoColumnDetail('Would sync', "<fg=green>{$synced}</>");
        } else {
            $this->components->twoColumnDetail('Synced', "<fg=green>{$synced}</>");
        }

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

    protected function showDryRunInfo($model): void
    {
        // In verbose mode, show details
        if ($this->output->isVerbose()) {
            $email = method_exists($model, 'getNewsletterEmail')
                ? $model->getNewsletterEmail()
                : 'N/A';

            $lists = method_exists($model, 'getNewsletterLists')
                ? $model->getNewsletterLists()
                : [];

            $this->line("  → Would sync: {$email} to lists: " . implode(', ', $lists));
        }
    }

    protected function getDefaultModel(): string
    {
        return config('auth.providers.users.model', 'App\\Models\\User');
    }
}
