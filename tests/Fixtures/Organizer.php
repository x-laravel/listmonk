<?php

namespace XLaravel\Listmonk\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Listmonk\Concerns\Subscribable;
use XLaravel\Listmonk\Contracts\HasSubscriber;

/**
 * @property int $id
 * @property string $title
 * @property string $email
 */
class Organizer extends Model implements HasSubscriber
{
    use Subscribable;

    public const int ORGANIZERS = 3;

    public $timestamps = false;

    protected $guarded = [];

    public function subscriberEmail(): ?string
    {
        return $this->email;
    }

    public function subscriberName(): string
    {
        return $this->title;
    }

    public function subscriberLists(): array
    {
        return [self::ORGANIZERS];
    }
}
