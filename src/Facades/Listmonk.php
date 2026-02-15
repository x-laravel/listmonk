<?php

namespace XLaravel\Listmonk\Facades;

use Illuminate\Support\Facades\Facade;
use XLaravel\Listmonk\Services\Lists;
use XLaravel\Listmonk\Services\NewsletterManager;
use XLaravel\Listmonk\Services\Subscribers;

/**
 * @method static Subscribers subscribers()
 * @method static Lists lists()
 * @method static NewsletterManager newsletter()
 *
 * @see \XLaravel\Listmonk\Listmonk
 */
class Listmonk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'listmonk';
    }
}
