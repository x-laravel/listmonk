<?php

namespace XLaravel\Listmonk\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Bus;
use LogicException;
use XLaravel\Listmonk\Contracts\HasSubscriber;
use XLaravel\Listmonk\Jobs\SyncSubscriber;
use XLaravel\Listmonk\Models\Subscriber;
use XLaravel\Listmonk\Support\ListmonkConfig;
use XLaravel\Listmonk\Support\SubscriberSync;

/**
 * @mixin Model
 */
trait Subscribable
{
    public static function bootSubscribable(): void
    {
        static::saved(function (Model $model) {
            /** @var Model&self $model */
            if ($model->wasRecentlyCreated || $model->wasChanged()) {
                $model->syncSubscriberWhenChanged();
            }
        });

        static::deleted(function (Model $model) {
            /** @var Model&self $model */
            $model->syncSubscriberWhenChanged();
        });
    }

    /**
     * @return MorphOne<Subscriber, $this>
     */
    public function subscriber(): MorphOne
    {
        return $this->morphOne(ListmonkConfig::subscriberModel(), 'subscribable');
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriberAttributes(): array
    {
        return [];
    }

    public function syncSubscriber(): void
    {
        $this->subscribableModel();

        if (! ListmonkConfig::enabled()) {
            return;
        }

        $key = $this->getKey();

        if (! is_int($key) && ! is_string($key)) {
            return;
        }

        Bus::dispatch((new SyncSubscriber($this->getMorphClass(), $key))->afterCommit());
    }

    protected function syncSubscriberWhenChanged(): void
    {
        $model = $this->subscribableModel();

        if (! ListmonkConfig::enabled()) {
            return;
        }

        $subscriber = $this->subscriber()->first();
        $hash = SubscriberSync::hash($model);

        if ($hash === $subscriber?->hash) {
            return;
        }

        $this->syncSubscriber();
    }

    protected function subscribableModel(): Model&HasSubscriber
    {
        if (! $this instanceof HasSubscriber) {
            throw new LogicException(sprintf('Implement %s on [%s].', HasSubscriber::class, static::class));
        }

        return $this;
    }
}
