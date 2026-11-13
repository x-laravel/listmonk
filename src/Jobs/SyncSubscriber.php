<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use XLaravel\Listmonk\Support\ListmonkConfig;
use XLaravel\Listmonk\Support\SubscriberSync;

final class SyncSubscriber implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $type,
        public readonly int|string $key,
    ) {
        $this->onConnection(ListmonkConfig::queueConnection());
        $this->onQueue(ListmonkConfig::queueName());
    }

    public function handle(SubscriberSync $sync): void
    {
        $sync->handle($this->type, $this->key);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("listmonk:{$this->type}:{$this->key}"))->releaseAfter(5)->expireAfter(60),
        ];
    }
}
