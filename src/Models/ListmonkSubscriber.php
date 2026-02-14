<?php

namespace XLaravel\Listmonk\Models;

use Illuminate\Database\Eloquent\Model;

class ListmonkSubscriber extends Model
{
    protected $fillable = [
        'listmonk_id',
        'email',
        'lists',
    ];

    protected $casts = [
        'lists' => 'array',
    ];

    public function subscriber()
    {
        return $this->morphTo();
    }
}
