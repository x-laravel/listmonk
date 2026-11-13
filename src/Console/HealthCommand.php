<?php

namespace XLaravel\Listmonk\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use XLaravel\Listmonk\Client;
use XLaravel\Listmonk\Support\ListmonkConfig;

class HealthCommand extends Command
{
    protected $signature = 'listmonk:health';

    protected $description = 'Check that Listmonk answers and accepts the API user';

    public function handle(Client $client): int
    {
        if (! ListmonkConfig::enabled()) {
            $this->components->error('Listmonk is not enabled.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Url', ListmonkConfig::url());
        $this->components->twoColumnDetail('Username', ListmonkConfig::username());

        try {
            $client->findSubscriber('health@listmonk.invalid');
        } catch (RequestException $exception) {
            $message = $exception->response->json('message');

            $this->components->error(sprintf('Listmonk answered %d: %s', $exception->response->status(), is_string($message) ? $message : 'no message'));

            return self::FAILURE;
        } catch (ConnectionException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Listmonk accepts the API user.');

        return self::SUCCESS;
    }
}
