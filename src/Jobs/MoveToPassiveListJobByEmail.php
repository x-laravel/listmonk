<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Concerns\ConfiguresQueue;
use XLaravel\Listmonk\Services\NewsletterManager;

class MoveToPassiveListJobByEmail implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels, ConfiguresQueue;

    public function __construct(
        protected string $email,
        protected int $passiveListId
    ) {
        $this->configureJob();
    }

    public function handle(NewsletterManager $service): void
    {
        try {
            $service->moveToPassiveListByEmail($this->email, $this->passiveListId);
        } catch (\Exception $e) {
            Log::error('Move to passive list by email job failed', [
                'email' => $this->email,
                'passive_list_id' => $this->passiveListId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
