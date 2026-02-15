<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Events\SubscriberSubscribed;
use XLaravel\Listmonk\Events\SubscriberSynced;
use XLaravel\Listmonk\Events\SubscriberSyncFailed;
use XLaravel\Listmonk\Events\SubscriberUnsubscribed;

class NewsletterManager
{
    public function __construct(protected Subscribers $subscribers)
    {
    }

    /**
     * Sync a subscriber to Listmonk.
     * - If exists: update name, attributes, and merge lists
     * - If not exists: create new subscriber
     */
    public function sync(NewsletterSubscriber $model): void
    {
        try {
            $email = $model->getNewsletterEmail();
            $this->validateEmail($email);

            $remote = $this->findByEmail($email);

            if ($remote) {
                $nameColumn = $model->getNewsletterNameColumn();
                $mergedLists = $this->mergeLists($this->extractListIds($remote), $model->getNewsletterLists());

                $payload = $this->buildPayload(
                    email: $model->getNewsletterEmail(),
                    name: $model->{$nameColumn} ?? '',
                    lists: $mergedLists,
                    attribs: $model->getNewsletterAttributes(),
                );

                $response = $this->subscribers->update($remote['id'], $payload);
                event(new SubscriberSynced($model, $response));
            } else {
                $nameColumn = $model->getNewsletterNameColumn();

                $payload = $this->buildPayload(
                    email: $model->getNewsletterEmail(),
                    name: $model->{$nameColumn} ?? '',
                    lists: $model->getNewsletterLists(),
                    attribs: $model->getNewsletterAttributes(),
                );

                $response = $this->subscribers->create($payload);
                event(new SubscriberSubscribed($model, $response));
            }
        } catch (\Exception $e) {
            Log::error('Listmonk sync failed', [
                'email' => $model->getNewsletterEmail(),
                'model' => get_class($model),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            event(new SubscriberSyncFailed($model, $e));

            throw $e;
        }
    }

    /**
     * Update only specified fields, preserving existing attributes and lists.
     */
    public function updatePartial(NewsletterSubscriber $model, array $fields = ['email', 'name']): void
    {
        $email = $model->getNewsletterEmail();
        $this->validateEmail($email);

        $remote = $this->findByEmail($email);

        if (!$remote) {
            $this->sync($model);
            return;
        }

        $nameColumn = $model->getNewsletterNameColumn();

        $payload = $this->buildPayload(
            email: in_array('email', $fields) ? $model->getNewsletterEmail() : $remote['email'],
            name: in_array('name', $fields) ? ($model->{$nameColumn} ?? '') : $remote['name'],
            lists: $this->extractListIds($remote),
            attribs: $remote['attribs'] ?? [],
            status: $remote['status'] ?? 'enabled',
        );

        $this->subscribers->update($remote['id'], $payload);

        event(new SubscriberSynced($model, $payload));
    }

    /**
     * Unsubscribe a subscriber from all lists.
     * Deletes the subscriber from Listmonk completely.
     */
    public function unsubscribe(NewsletterSubscriber $model): void
    {
        $deleted = $this->unsubscribeByEmail($model->getNewsletterEmail());

        if ($deleted) {
            event(new SubscriberUnsubscribed($model));
        }
    }

    /**
     * Unsubscribe a subscriber by email address.
     * Deletes the subscriber from Listmonk completely.
     *
     * @return bool Whether a subscriber was found and deleted.
     */
    public function unsubscribeByEmail(string $email): bool
    {
        $this->validateEmail($email);

        $remote = $this->findByEmail($email);

        if (!$remote) {
            return false;
        }

        $this->subscribers->delete($remote['id']);

        return true;
    }

    /**
     * Move subscriber to passive list (for deleted/inactive users).
     */
    public function moveToPassiveList(NewsletterSubscriber $model, int $passiveListId): void
    {
        $this->moveToPassiveListByEmail($model->getNewsletterEmail(), $passiveListId);
    }

    /**
     * Move subscriber to passive list by email address.
     */
    public function moveToPassiveListByEmail(string $email, int $passiveListId): void
    {
        $this->validateEmail($email);

        $remote = $this->findByEmail($email);

        if (!$remote) {
            return;
        }

        $payload = $this->buildPayload(
            email: $remote['email'],
            name: $remote['name'],
            lists: [$passiveListId],
            attribs: $remote['attribs'] ?? [],
        );

        $this->subscribers->update($remote['id'], $payload);
    }

    /**
     * Sync multiple subscribers in batch.
     */
    public function syncMany(iterable $models): array
    {
        $results = ['synced' => 0, 'failed' => 0, 'errors' => []];

        foreach ($models as $model) {
            try {
                $this->sync($model);
                $results['synced']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'model' => get_class($model),
                    'email' => $model->getNewsletterEmail(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Find a subscriber by email address.
     */
    protected function findByEmail(string $email): ?array
    {
        $escapedEmail = str_replace("'", "''", $email);

        $data = $this->subscribers->get(
            query: "subscribers.email = '{$escapedEmail}'"
        );

        $results = $data['results'] ?? [];

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Build a subscriber payload for Listmonk API.
     */
    protected function buildPayload(
        string $email,
        string $name,
        array $lists,
        array $attribs,
        string $status = 'enabled',
    ): array {
        return [
            'email' => $email,
            'name' => $name,
            'status' => $status,
            'lists' => $lists,
            'attribs' => $attribs,
            'preconfirm_subscriptions' => config('listmonk.preconfirm_subscriptions', true),
        ];
    }

    protected function validateEmail(string $email): void
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$email}");
        }
    }

    protected function extractListIds(array $remote): array
    {
        return collect(Arr::get($remote, 'lists', []))
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
    }

    protected function mergeLists(array $existing, array $new): array
    {
        return array_values(array_unique([...$existing, ...$new]));
    }
}
