<?php

namespace XLaravel\Listmonk\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Listmonk\Concerns\Subscribable;

class Guest extends Model
{
    use Subscribable;

    public $timestamps = false;

    protected $guarded = [];
}
