<?php

namespace XLaravel\Listmonk\Facades;

use Illuminate\Support\Facades\Facade;

class Listmonk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'listmonk';
    }
}
