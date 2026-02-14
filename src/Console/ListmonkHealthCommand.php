<?php

namespace XLaravel\Listmonk\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;

class ListmonkHealthCommand extends Command
{
    protected $signature = 'listmonk:health';

    protected $description = 'Check Listmonk API connection and health';

    public function handle(PendingRequest $client): int
    {
        $this->info('Checking Listmonk API health...');
        $this->newLine();

        try {
            // Test connection
            $response = $client->get('/api/health');

            if ($response->successful()) {
                $this->components->info('✓ Listmonk API is healthy and accessible');

                // Show configuration
                $this->newLine();
                $this->components->twoColumnDetail('Base URL', config('listmonk.base_url'));
                $this->components->twoColumnDetail('API User', config('listmonk.api_user'));
                $this->components->twoColumnDetail('Queue Enabled', config('listmonk.queue.enabled') ? 'Yes' : 'No');
                $this->components->twoColumnDetail('Preconfirm Subscriptions', config('listmonk.preconfirm_subscriptions') ? 'Yes' : 'No');

                return self::SUCCESS;
            }

            $this->components->error('✗ Listmonk API returned status: ' . $response->status());
            $this->line($response->body());

            return self::FAILURE;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->components->error('✗ Cannot connect to Listmonk API');
            $this->line('Error: ' . $e->getMessage());
            $this->newLine();
            $this->components->warn('Please check:');
            $this->line('  - LISTMONK_BASE_URL is correct');
            $this->line('  - Listmonk server is running');
            $this->line('  - Network connectivity');

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->components->error('✗ Unexpected error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
