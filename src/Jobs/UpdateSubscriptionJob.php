<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Concerns\ConfiguresQueue;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Services\NewsletterManager;

class UpdateSubscriptionJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels, ConfiguresQueue;

    public function __construct(
        protected NewsletterSubscriber $model
    ) {
        $this->configureJob();
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
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
