<?php

namespace XLaravel\Listmonk;

use Illuminate\Contracts\Container\Container;
use XLaravel\Listmonk\Services\Lists;
use XLaravel\Listmonk\Services\NewsletterManager;
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

    public function lists(): Lists
    {
        return $this->app->make(Lists::class);
    }

    public function newsletter(): NewsletterManager
    {
        return $this->app->make(NewsletterManager::class);
    }
}
