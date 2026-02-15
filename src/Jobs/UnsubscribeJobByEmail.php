<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Concerns\ConfiguresQueue;
use XLaravel\Listmonk\Services\NewsletterManager;

class UnsubscribeJobByEmail implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels, ConfiguresQueue;

    public function __construct(
        protected string $email
    ) {
        $this->configureJob();
    }

    public function handle(NewsletterManager $service): void
    {
        try {
            $service->unsubscribeByEmail($this->email);
        } catch (\Exception $e) {
            Log::error('Unsubscribe by email job failed', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
