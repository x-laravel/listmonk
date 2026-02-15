<?php

namespace XLaravel\Listmonk\Concerns;

trait ConfiguresQueue
{
    public int $tries;
    public array $backoff;

    protected function configureJob(): void
    {
        $queueConfig = config('listmonk.queue');

        // Connection
        if (!empty($queueConfig['connection'])) {
            $this->onConnection($queueConfig['connection']);
        }

        // Queue name
        if (!empty($queueConfig['queue'])) {
            $this->onQueue($queueConfig['queue']);
        }

        // Delay
        if (!empty($queueConfig['delay'])) {
            $this->delay(now()->addSeconds((int) $queueConfig['delay']));
        }

        // Tries
        $this->tries = (int) ($queueConfig['tries'] ?? 3);

        // Backoff
        $this->backoff = $this->parseBackoff(
            $queueConfig['backoff'] ?? '10,30,60'
        );
    }

    protected function parseBackoff(string|array $backoff): array
    {
        if (is_array($backoff)) {
            return $backoff;
        }

        return collect(explode(',', $backoff))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->values()
            ->toArray();
    }
}
