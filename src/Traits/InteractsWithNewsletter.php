<?php

namespace XLaravel\Listmonk\Traits;

use XLaravel\Listmonk\Services\NewsletterManager;
use XLaravel\Listmonk\Jobs\SubscribeJob;
use XLaravel\Listmonk\Jobs\UnsubscribeJob;
use XLaravel\Listmonk\Jobs\UpdateSubscriptionJob;

trait InteractsWithNewsletter
{
    /**
     * Static flag to disable newsletter sync temporarily
     */
    protected static bool $newsletterSyncEnabled = true;

    /**
     * Newsletter email column name (override in model if different)
     */
    protected string $newsletterEmailColumn = 'email';

    /*
    |--------------------------------------------------------------------------
    | Column Definitions
    |--------------------------------------------------------------------------
    */

    public function getNewsletterEmailColumn(): string
    {
        return $this->newsletterEmailColumn ?? 'email';
    }

    public function getNewsletterNameColumn(): string
    {
        return 'name'; // Override in model if different
    }

    public function getNewsletterPassiveListId(): ?int
    {
        return config('listmonk.passive_list_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getNewsletterEmail(): string
    {
        $column = $this->getNewsletterEmailColumn();
        return $this->{$column} ?? '';
    }

    /**
     * Get custom attributes to sync with Listmonk.
     *
     * Override this method in your model to add custom fields:
     *
     * Example:
     * public function getNewsletterAttributes(): array
     * {
     *     return [
     *         'plan' => $this->subscription_plan ?? '',
     *         'country' => $this->country ?? '',
     *         'language' => $this->locale ?? 'en',
     *         'registered_at' => $this->created_at?->toIso8601String(),
     *     ];
     * }
     */
    public function getNewsletterAttributes(): array
    {
        return [];
    }

    /**
     * Get list IDs to subscribe to.
     *
     * Override this method in your model to customize lists:
     *
     * Example:
     * public function getNewsletterLists(): array
     * {
     *     $lists = [];
     *
     *     // Main newsletter list
     *     $lists[] = 1;
     *
     *     // Add premium list if user is premium
     *     if ($this->is_premium) {
     *         $lists[] = 2;
     *     }
     *
     *     // Add language-specific list
     *     if ($this->locale === 'tr') {
     *         $lists[] = 3;
     *     }
     *
     *     return $lists;
     * }
     */
    public function getNewsletterLists(): array
    {
        return config('listmonk.default_lists', []);
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

        app(NewsletterManager::class)->sync($this);
    }

    public function unsubscribeFromNewsletter(): void
    {
        if (config('listmonk.queue.enabled')) {
            UnsubscribeJob::dispatch($this);
            return;
        }

        app(NewsletterManager::class)->unsubscribe($this);
    }

    public function updateNewsletterSubscription(): void
    {
        if (config('listmonk.queue.enabled')) {
            UpdateSubscriptionJob::dispatch($this);
            return;
        }

        app(NewsletterManager::class)->sync($this);
    }

    public function moveToPassiveList(): void
    {
        $passiveListId = $this->getNewsletterPassiveListId();

        if ($passiveListId === null) {
            $this->unsubscribeFromNewsletter();
            return;
        }

        app(NewsletterManager::class)->moveToPassiveList($this, $passiveListId);
    }

    /*
    |--------------------------------------------------------------------------
    | Static Sync Control
    |--------------------------------------------------------------------------
    */

    public static function withoutNewsletterSync(callable $callback): mixed
    {
        $previous = static::$newsletterSyncEnabled;
        static::$newsletterSyncEnabled = false;

        try {
            return $callback();
        } finally {
            static::$newsletterSyncEnabled = $previous;
        }
    }

    public static function isNewsletterSyncEnabled(): bool
    {
        return static::$newsletterSyncEnabled;
    }

    /*
    |--------------------------------------------------------------------------
    | Instance Sync Control
    |--------------------------------------------------------------------------
    */

    public function shouldSyncNewsletter(): bool
    {
        // Only check static flag
        return static::isNewsletterSyncEnabled();
    }
}
