<?php

namespace XLaravel\Listmonk\Traits;

use XLaravel\Listmonk\Services\Subscribers;
use XLaravel\Listmonk\Jobs\SubscribeJob;
use XLaravel\Listmonk\Jobs\UnsubscribeJob;
use XLaravel\Listmonk\Jobs\UpdateSubscriptionJob;

trait InteractsWithNewsletter
{
    public function getNewsletterEmail(): string
    {
        return $this->email;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    public function subscribeToNewsletter(): void
    {
        if (config('listmonk.queue.enabled')) {
            SubscribeJob::dispatch($this);
            return;
        }

        app(Subscribers::class)->sync($this);
    }

    public function unsubscribeFromNewsletter(): void
    {
        if (config('listmonk.queue.enabled')) {
            UnsubscribeJob::dispatch($this);
            return;
        }

        app(Subscribers::class)->unsubscribe($this);
    }

    public function updateNewsletterSubscription(): void
    {
        if (config('listmonk.queue.enabled')) {
            UpdateSubscriptionJob::dispatch($this);
            return;
        }

        app(Subscribers::class)->sync($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Auto Sync
    |--------------------------------------------------------------------------
    */
    public static function bootInteractsWithNewsletter()
    {
        static::updated(function ($model) {
            if (!$model instanceof \XLaravel\Listmonk\Contracts\NewsletterSubscriber) {
                return;
            }

            if ($model->wasChanged(['name', 'email'])) {
                $model->updateNewsletterSubscription();
            }
        });
    }
}
