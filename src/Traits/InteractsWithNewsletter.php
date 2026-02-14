<?php

namespace XLaravel\Listmonk\Traits;

use XLaravel\Listmonk\Jobs\SyncSubscriber;
use XLaravel\Listmonk\Services\Subscribers;

trait InteractsWithNewsletter
{
    public function subscribeToNewsletter(): void
    {
        if (config('listmonk.queue', true)) {
            SyncSubscriber::dispatch($this);
            return;
        }

        app(Subscribers::class)->sync($this);
    }

    public function unsubscribeFromNewsletter(): void
    {
        app(Subscribers::class)->unsubscribe($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Auto Sync on Update (optional)
    |--------------------------------------------------------------------------
    */

    public static function bootInteractsWithListmonk()
    {
        static::updated(function ($model) {
            if (! $model instanceof \XLaravel\Listmonk\Contracts\NewsletterSubscriber) {
                return;
            }

            // sadece belirli alanlar değiştiyse sync
            if ($model->wasChanged(['name', 'email'])) {
                $model->subscribeToNewsletter();
            }
        });
    }
}
