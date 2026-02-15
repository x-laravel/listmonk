<?php

namespace XLaravel\Listmonk\Observers;

use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Services\NewsletterManager;

class NewsletterSubscriberObserver
{
    /**
     * Handle the "created" event.
     * Subscribe the model to newsletter.
     */
    public function created(NewsletterSubscriber $model): void
    {
        if (!$this->shouldSync($model)) {
            return;
        }

        $model->subscribeToNewsletter();
    }

    /**
     * Handle the "updated" event.
     * Partially update only email/name if they changed.
     */
    public function updated(NewsletterSubscriber $model): void
    {
        if (!$this->shouldSync($model)) {
            return;
        }

        $emailColumn = $model->getNewsletterEmailColumn();
        $nameColumn = $model->getNewsletterNameColumn();

        $changedFields = [];

        // Check if email column changed
        $emailChanged = $model->wasChanged($emailColumn);
        if ($emailChanged) {
            $changedFields[] = 'email';
        }

        // Check if name column changed
        if ($model->wasChanged($nameColumn)) {
            $changedFields[] = 'name';
        }

        if (empty($changedFields)) {
            return;
        }

        // If email changed, handle old email cleanup
        if ($emailChanged) {
            $this->handleEmailChange($model, $emailColumn);
            return;
        }

        // Partial update - only name, preserve attribs and lists
        if (config('listmonk.queue.enabled')) {
            \XLaravel\Listmonk\Jobs\UpdateSubscriptionJob::dispatch($model);
        } else {
            app(NewsletterManager::class)->updatePartial($model, $changedFields);
        }
    }

    /**
     * Handle email change: unsubscribe old email and subscribe new email.
     */
    protected function handleEmailChange(NewsletterSubscriber $model, string $emailColumn): void
    {
        $oldEmail = $model->getOriginal($emailColumn);

        $behavior = config('listmonk.email_change_behavior', 'delete');
        $passiveListId = $model->getNewsletterPassiveListId();

        // Handle old email based on configuration
        if ($behavior === 'passive' && $passiveListId !== null) {
            // Move old email to passive list
            if (config('listmonk.queue.enabled')) {
                \XLaravel\Listmonk\Jobs\MoveToPassiveListJobByEmail::dispatch($oldEmail, $passiveListId);
            } else {
                app(NewsletterManager::class)->moveToPassiveListByEmail($oldEmail, $passiveListId);
            }
        } else {
            // Delete old email completely
            if (config('listmonk.queue.enabled')) {
                \XLaravel\Listmonk\Jobs\UnsubscribeJobByEmail::dispatch($oldEmail);
            } else {
                app(NewsletterManager::class)->unsubscribeByEmail($oldEmail);
            }
        }

        // Subscribe new email
        $model->subscribeToNewsletter();
    }

    /**
     * Handle the "deleted" event.
     * Move to passive list or unsubscribe.
     */
    public function deleted(NewsletterSubscriber $model): void
    {
        if (!$this->shouldSync($model)) {
            return;
        }

        // Check if this is a soft delete
        if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
            // Soft delete - move to passive list
            $model->moveToPassiveList();
        } else {
            // Force delete - unsubscribe
            $model->moveToPassiveList();
        }
    }

    /**
     * Handle the "forceDeleted" event.
     * Unsubscribe or move to passive list.
     */
    public function forceDeleted(NewsletterSubscriber $model): void
    {
        if (!$this->shouldSync($model)) {
            return;
        }

        $model->moveToPassiveList();
    }

    /**
     * Handle the "restored" event.
     * Re-subscribe the model.
     */
    public function restored(NewsletterSubscriber $model): void
    {
        if (!$this->shouldSync($model)) {
            return;
        }

        $model->subscribeToNewsletter();
    }

    /**
     * Determine if the model should be synced.
     */
    protected function shouldSync(NewsletterSubscriber $model): bool
    {
        // Check if model uses InteractsWithNewsletter trait
        $traits = class_uses_recursive($model);

        if (!in_array(\XLaravel\Listmonk\Traits\InteractsWithNewsletter::class, $traits)) {
            return false;
        }

        // Check if model has shouldSyncNewsletter method
        if (method_exists($model, 'shouldSyncNewsletter')) {
            return $model->shouldSyncNewsletter();
        }

        return true;
    }
}
