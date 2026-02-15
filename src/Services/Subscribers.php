<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Events\SubscriberSubscribed;
use XLaravel\Listmonk\Events\SubscriberSynced;
use XLaravel\Listmonk\Events\SubscriberSyncFailed;
use XLaravel\Listmonk\Events\SubscriberUnsubscribed;
use XLaravel\Listmonk\Exceptions\ListmonkApiException;
use XLaravel\Listmonk\Exceptions\ListmonkConnectionException;

class Subscribers
{
    public function __construct(protected PendingRequest $client)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC API
    |--------------------------------------------------------------------------
    */

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

            $remote = $this->fetchRemoteByEmail($email);

            if ($remote) {
                $response = $this->updateRemote($remote, $model);
                event(new SubscriberSynced($model, $response));
            } else {
                $response = $this->createRemote($model);
                event(new SubscriberSubscribed($model, $response));
            }
        } catch (\Exception $e) {
            Log::error('Listmonk sync failed', [
                'email' => $model->getNewsletterEmail(),
                'model' => get_class($model),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

        $remote = $this->fetchRemoteByEmail($email);

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

        $this->putSubscriber($remote['id'], $payload, 'partially update');

        event(new SubscriberSynced($model, $payload));
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

        $remote = $this->fetchRemoteByEmail($email);

        if (!$remote) {
            return;
        }

        $payload = $this->buildPayload(
            email: $remote['email'],
            name: $remote['name'],
            lists: [$passiveListId],
            attribs: $remote['attribs'] ?? [],
        );

        $this->putSubscriber($remote['id'], $payload, 'move to passive list');
    }

    /**
     * Unsubscribe a subscriber by email address.
     * Deletes the subscriber from Listmonk completely.
     */
    public function unsubscribeByEmail(string $email): void
    {
        $this->validateEmail($email);

        $remote = $this->fetchRemoteByEmail($email);

        if (!$remote) {
            return;
        }

        $response = $this->apiCall(
            fn () => $this->client->delete("/api/subscribers/{$remote['id']}"),
            'delete'
        );
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
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Unsubscribe a subscriber from all lists.
     * Deletes the subscriber from Listmonk completely.
     */
    public function unsubscribe(NewsletterSubscriber $model): void
    {
        $this->unsubscribeByEmail($model->getNewsletterEmail());

        event(new SubscriberUnsubscribed($model));
    }

    /*
    |--------------------------------------------------------------------------
    | REMOTE API OPERATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Fetch a subscriber from Listmonk by email address.
     *
     * @return array|null Returns subscriber data or null if not found
     * @throws ListmonkApiException
     * @throws ListmonkConnectionException
     */
    public function fetchRemoteByEmail(string $email): ?array
    {
        $escapedEmail = str_replace("'", "''", $email);

        $response = $this->apiCall(
            fn () => $this->client->get('/api/subscribers', [
                'query' => "subscribers.email = '{$escapedEmail}'"
            ]),
            'fetch'
        );

        $results = $response->json('data.results', []);

        return !empty($results) ? $results[0] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL METHODS
    |--------------------------------------------------------------------------
    */

    protected function createRemote(NewsletterSubscriber $model): array
    {
        $nameColumn = $model->getNewsletterNameColumn();

        $payload = $this->buildPayload(
            email: $model->getNewsletterEmail(),
            name: $model->{$nameColumn} ?? '',
            lists: $model->getNewsletterLists(),
            attribs: $model->getNewsletterAttributes(),
        );

        return $this->apiCall(
            fn () => $this->client->post('/api/subscribers', $payload),
            'create'
        )->json();
    }

    protected function updateRemote(array $remote, NewsletterSubscriber $model): array
    {
        $nameColumn = $model->getNewsletterNameColumn();
        $mergedLists = $this->mergeLists($this->extractListIds($remote), $model->getNewsletterLists());

        $payload = $this->buildPayload(
            email: $model->getNewsletterEmail(),
            name: $model->{$nameColumn} ?? '',
            lists: $mergedLists,
            attribs: $model->getNewsletterAttributes(),
        );

        return $this->apiCall(
            fn () => $this->client->put("/api/subscribers/{$remote['id']}", $payload),
            'update'
        )->json();
    }

    /**
     * Update an existing subscriber via PUT.
     */
    protected function putSubscriber(int $id, array $payload, string $operation): void
    {
        $this->apiCall(
            fn () => $this->client->put("/api/subscribers/{$id}", $payload),
            $operation
        );
    }

    /**
     * Execute an API call with unified error handling.
     *
     * @throws ListmonkApiException
     * @throws ListmonkConnectionException
     */
    protected function apiCall(\Closure $request, string $operation): \Illuminate\Http\Client\Response
    {
        try {
            $response = $request();

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to {$operation} subscriber: " . $response->body(),
                    $response->status()
                );
            }

            return $response;
        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $message = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')
                ? "Request timed out while connecting to Listmonk API. Please check network connectivity or increase timeout."
                : "Cannot connect to Listmonk API: " . $e->getMessage();

            throw new ListmonkConnectionException($message, 0, $e);
        } catch (\Exception $e) {
            throw new ListmonkApiException(
                "Unexpected error during {$operation}: " . $e->getMessage(),
                0,
                $e
            );
        }
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
