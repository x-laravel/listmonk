<?php

namespace XLaravel\Listmonk;

use Illuminate\Contracts\Container\Container;
use XLaravel\Listmonk\Services\Subscribers;

class Listmonk
{
    public function __construct(
        protected Container $app
    )
    {
    }

    public function subscribers(): Subscribers
    {
        return $this->app->make(Subscribers::class);
    }
}
