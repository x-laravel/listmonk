<?php

namespace XLaravel\Listmonk\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use XLaravel\Listmonk\Concerns\Subscribable;
use XLaravel\Listmonk\Contracts\HasSubscriber;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $city
 * @property bool $newsletter
 * @property int $logins
 */
class User extends Model implements HasSubscriber
{
    use SoftDeletes;
    use Subscribable;

    public const int ANNOUNCEMENTS = 1;

    public const int NEWSLETTER = 2;

    public $timestamps = false;

    protected $guarded = [];

    protected $attributes = [
        'newsletter' => true,
    ];

    public function subscriberEmail(): ?string
    {
        return $this->email;
    }

    public function subscriberName(): string
    {
        return $this->name;
    }

    public function subscriberLists(): array
    {
        return $this->newsletter ? [self::ANNOUNCEMENTS, self::NEWSLETTER] : [self::ANNOUNCEMENTS];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriberAttributes(): array
    {
        return ['city' => $this->city];
    }

    protected function casts(): array
    {
        return ['newsletter' => 'boolean'];
    }
}
