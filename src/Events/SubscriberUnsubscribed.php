<?php

namespace XLaravel\Listmonk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;

class SubscriberUnsubscribed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $model
    ) {
    }
}
