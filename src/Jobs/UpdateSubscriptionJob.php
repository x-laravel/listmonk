<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Services\NewsletterManager;

class UpdateSubscriptionJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries;
    public array $backoff;

    public function __construct(
        protected NewsletterSubscriber $model
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

    public function handle(NewsletterManager $service): void
    {
        try {
            $service->sync($this->model);
        } catch (\Exception $e) {
            Log::error('Update subscription job failed', [
                'model' => get_class($this->model),
                'email' => $this->model->getNewsletterEmail(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
