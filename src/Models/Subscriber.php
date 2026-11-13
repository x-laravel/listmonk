<?php

namespace XLaravel\Listmonk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use XLaravel\Listmonk\Support\ListmonkConfig;
use XLaravel\Listmonk\Support\MissingMorphTargets;
use XLaravel\Listmonk\Support\SubscriberSync;

/**
 * @property int $id
 * @property string $subscribable_type
 * @property int|string $subscribable_id
 * @property int $listmonk_id
 * @property string $email
 * @property list<int> $lists
 * @property string $hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Subscriber extends Model
{
    use Prunable;

    protected $fillable = [
        'listmonk_id',
        'email',
        'lists',
        'hash',
    ];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return MissingMorphTargets::where(static::query(), 'subscribable');
    }

    public function getTable(): string
    {
        return ListmonkConfig::subscriberTable();
    }

    public function getConnectionName(): ?string
    {
        return ListmonkConfig::connection() ?? parent::getConnectionName();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function pruning(): void
    {
        app(SubscriberSync::class)->detach($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'listmonk_id' => 'integer',
            'lists' => 'array',
        ];
    }
}
