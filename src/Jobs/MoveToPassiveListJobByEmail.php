<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Services\Subscribers;

class MoveToPassiveListJobByEmail implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries;
    public array $backoff;

    public function __construct(
        protected string $email,
        protected int $passiveListId
    ) {
        $this->configureJob();
    }

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

    public function handle(Subscribers $service): void
    {
        try {
            $service->moveToPassiveListByEmail($this->email, $this->passiveListId);
        } catch (\Exception $e) {
            Log::error('Move to passive list by email job failed', [
                'email' => $this->email,
                'passive_list_id' => $this->passiveListId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
