<?php

namespace XLaravel\Listmonk\Observers;

use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Services\Subscribers;

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

        Log::debug('NewsletterSubscriberObserver: created event', [
            'model' => get_class($model),
            'email' => $model->getNewsletterEmail()
        ]);

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

        Log::debug('NewsletterSubscriberObserver: updated event', [
            'model' => get_class($model),
            'email' => $model->getNewsletterEmail(),
            'changed_fields' => $changedFields,
            'email_column' => $emailColumn,
            'name_column' => $nameColumn
        ]);

        // If email changed, handle old email cleanup
        if ($emailChanged) {
            $this->handleEmailChange($model, $emailColumn);
            return;
        }

        // Partial update - only name, preserve attribs and lists
        if (config('listmonk.queue.enabled')) {
            \XLaravel\Listmonk\Jobs\UpdateSubscriptionJob::dispatch($model);
        } else {
            app(Subscribers::class)->updatePartial($model, $changedFields);
        }
    }

    /**
     * Handle email change: unsubscribe old email and subscribe new email.
     */
    protected function handleEmailChange(NewsletterSubscriber $model, string $emailColumn): void
    {
        $oldEmail = $model->getOriginal($emailColumn);
        $newEmail = $model->{$emailColumn};

        Log::info('NewsletterSubscriberObserver: email changed', [
            'model' => get_class($model),
            'old_email' => $oldEmail,
            'new_email' => $newEmail
        ]);

        // Unsubscribe old email
        if (config('listmonk.queue.enabled')) {
            \XLaravel\Listmonk\Jobs\UnsubscribeJobByEmail::dispatch($oldEmail);
        } else {
            app(\XLaravel\Listmonk\Services\Subscribers::class)->unsubscribeByEmail($oldEmail);
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
            Log::debug('NewsletterSubscriberObserver: soft deleted event', [
                'model' => get_class($model),
                'email' => $model->getNewsletterEmail()
            ]);

            $model->moveToPassiveList();
        } else {
            // Force delete - unsubscribe
            Log::debug('NewsletterSubscriberObserver: force deleted event', [
                'model' => get_class($model),
                'email' => $model->getNewsletterEmail()
            ]);

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

        Log::debug('NewsletterSubscriberObserver: force deleted event', [
            'model' => get_class($model),
            'email' => $model->getNewsletterEmail()
        ]);

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

        Log::debug('NewsletterSubscriberObserver: restored event', [
            'model' => get_class($model),
            'email' => $model->getNewsletterEmail()
        ]);

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
