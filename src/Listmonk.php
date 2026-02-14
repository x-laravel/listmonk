<?php

namespace XLaravel\Listmonk;

use Illuminate\Contracts\Container\Container;
use XLaravel\Listmonk\Http\Client;
use XLaravel\Listmonk\Services\Subscribers;

class Listmonk
{
    protected Client $client;

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