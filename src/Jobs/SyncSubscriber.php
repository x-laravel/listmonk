<?php

namespace XLaravel\Listmonk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Services\Subscribers;

class SyncSubscriber implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        protected NewsletterSubscriber $model
    ) {}

    public function handle(Subscribers $service): void
    {
        $service->sync($this->model);
    }
}
