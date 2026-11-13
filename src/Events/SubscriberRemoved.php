<?php

namespace XLaravel\Listmonk\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use XLaravel\Listmonk\Models\Subscriber;

final readonly class SubscriberRemoved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Subscriber $subscriber,
    ) {
    }
}
