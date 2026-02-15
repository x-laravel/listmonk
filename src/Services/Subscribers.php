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
    | MAIN SYNC METHOD
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

            // Validate email format
            $this->validateEmail($email);

            Log::debug('Starting Listmonk sync', [
                'email' => $email,
                'model' => get_class($model),
                'lists' => $model->getNewsletterLists()
            ]);

            // Fetch subscriber from Listmonk by email
            $remote = $this->fetchRemoteByEmail($email);

            if ($remote) {
                // Subscriber exists - update it
                $response = $this->updateRemote($remote, $model);
                Log::debug('Subscriber updated in Listmonk', [
                    'email' => $email,
                    'listmonk_id' => $remote['id']
                ]);

                event(new SubscriberSynced($model, $response));
            } else {
                // Subscriber doesn't exist - create it
                $response = $this->createRemote($model);
                Log::debug('Subscriber created in Listmonk', [
                    'email' => $email,
                    'listmonk_id' => $response['data']['id'] ?? null
                ]);

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
     * Update only email and name fields, preserving existing attributes and lists.
     * This method fetches the current subscriber data and merges only email/name changes.
     */
    public function updatePartial(NewsletterSubscriber $model, array $fields = ['email', 'name']): void
    {
        try {
            $email = $model->getNewsletterEmail();

            // Validate email format
            $this->validateEmail($email);

            Log::debug('Starting partial Listmonk update', [
                'email' => $email,
                'fields' => $fields,
                'model' => get_class($model)
            ]);

            // Fetch current subscriber data
            $remote = $this->fetchRemoteByEmail($email);

            if (!$remote) {
                // Subscriber doesn't exist - do full sync instead
                Log::warning('Subscriber not found for partial update, doing full sync', [
                    'email' => $email
                ]);
                $this->sync($model);
                return;
            }

            // Prepare payload with only changed fields
            $nameColumn = $model->getNewsletterNameColumn();

            $payload = [
                'email' => in_array('email', $fields) ? $model->getNewsletterEmail() : $remote['email'],
                'name' => in_array('name', $fields) ? ($model->{$nameColumn} ?? '') : $remote['name'],
                'status' => $remote['status'] ?? 'enabled',
                'lists' => $this->extractListIds($remote), // Keep existing lists
                'attribs' => $remote['attribs'] ?? [], // Keep existing attributes
                'preconfirm_subscriptions' => config('listmonk.preconfirm_subscriptions', true),
            ];

            $response = $this->client->put("/api/subscribers/{$remote['id']}", $payload);

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to partially update subscriber: " . $response->body(),
                    $response->status()
                );
            }

            Log::debug('Subscriber partially updated in Listmonk', [
                'email' => $email,
                'listmonk_id' => $remote['id'],
                'updated_fields' => $fields
            ]);

            event(new SubscriberSynced($model, $response->json()));

        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Partial update failed', [
                'email' => $model->getNewsletterEmail(),
                'error' => $e->getMessage()
            ]);

            event(new SubscriberSyncFailed($model, $e));

            throw new ListmonkApiException(
                "Unexpected error during partial update: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Move subscriber to passive list (for deleted/inactive users).
     * Unsubscribes from all active lists and subscribes only to passive list.
     */
    public function moveToPassiveList(NewsletterSubscriber $model, int $passiveListId): void
    {
        $email = $model->getNewsletterEmail();
        $this->moveToPassiveListByEmail($email, $passiveListId);
    }

    /**
     * Move subscriber to passive list by email address.
     */
    public function moveToPassiveListByEmail(string $email, int $passiveListId): void
    {
        try {
            // Validate email format
            $this->validateEmail($email);

            Log::debug('Moving subscriber to passive list', [
                'email' => $email,
                'passive_list_id' => $passiveListId
            ]);

            $remote = $this->fetchRemoteByEmail($email);

            if (!$remote) {
                Log::debug('Subscriber not found for passive list move (already removed)', [
                    'email' => $email
                ]);
                return;
            }

            // Update subscriber with only passive list
            $payload = [
                'email' => $remote['email'],
                'name' => $remote['name'],
                'status' => 'enabled',
                'lists' => [$passiveListId], // Only passive list
                'attribs' => $remote['attribs'] ?? [],
                'preconfirm_subscriptions' => config('listmonk.preconfirm_subscriptions', true),
            ];

            $response = $this->client->put("/api/subscribers/{$remote['id']}", $payload);

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to move subscriber to passive list: " . $response->body(),
                    $response->status()
                );
            }

            Log::info('Subscriber moved to passive list', [
                'email' => $email,
                'listmonk_id' => $remote['id'],
                'passive_list_id' => $passiveListId
            ]);

        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Move to passive list failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            throw new ListmonkApiException(
                "Unexpected error moving to passive list: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Unsubscribe a subscriber by email address.
     * Deletes the subscriber from Listmonk completely.
     */
    public function unsubscribeByEmail(string $email): void
    {
        try {
            // Validate email format
            $this->validateEmail($email);

            Log::debug('Attempting to delete subscriber from Listmonk', [
                'email' => $email
            ]);

            $remote = $this->fetchRemoteByEmail($email);

            if (!$remote) {
                Log::debug('Subscriber not found in Listmonk for deletion (already deleted or never subscribed)', [
                    'email' => $email
                ]);
                return;
            }

            // Delete subscriber completely
            $response = $this->client->delete("/api/subscribers/{$remote['id']}");

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to delete subscriber: " . $response->body(),
                    $response->status()
                );
            }

            Log::info('Subscriber deleted from Listmonk', [
                'email' => $email,
                'listmonk_id' => $remote['id']
            ]);

        } catch (ListmonkApiException $e) {
            Log::error('Listmonk deletion failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during deletion', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync multiple subscribers in batch.
     */
    public function syncMany(iterable $models): array
    {
        $results = [
            'synced' => 0,
            'failed' => 0,
            'errors' => []
        ];

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

        Log::info('Bulk sync completed', $results);

        return $results;
    }

    /**
     * Unsubscribe a subscriber from all lists.
     * Deletes the subscriber from Listmonk completely.
     */
    public function unsubscribe(NewsletterSubscriber $model): void
    {
        $email = $model->getNewsletterEmail();
        $this->unsubscribeByEmail($email);
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
        try {
            // Escape single quotes to prevent SQL injection
            $escapedEmail = str_replace("'", "''", $email);

            $response = $this->client->get('/api/subscribers', [
                'query' => "subscribers.email = '{$escapedEmail}'"
            ]);

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to fetch subscriber [{$email}]: " . $response->body(),
                    $response->status()
                );
            }

            $results = $response->json('data.results', []);

            return !empty($results) ? $results[0] : null;

        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Check if it's a timeout
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')) {
                throw new ListmonkConnectionException(
                    "Request timed out while connecting to Listmonk API. Please check network connectivity or increase timeout.",
                    0,
                    $e
                );
            }

            throw new ListmonkConnectionException(
                "Cannot connect to Listmonk API: " . $e->getMessage(),
                0,
                $e
            );
        } catch (\Exception $e) {
            throw new ListmonkApiException(
                "Unexpected error fetching subscriber: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create a new subscriber in Listmonk.
     *
     * @return array API response
     * @throws ListmonkApiException
     */
    protected function createRemote(NewsletterSubscriber $model): array
    {
        try {
            $nameColumn = $model->getNewsletterNameColumn();

            $payload = [
                'email' => $model->getNewsletterEmail(),
                'name' => $model->{$nameColumn} ?? '',
                'status' => 'enabled',
                'lists' => $model->getNewsletterLists(),
                'attribs' => $model->getNewsletterAttributes(),
                'preconfirm_subscriptions' => config('listmonk.preconfirm_subscriptions', true),
            ];

            $response = $this->client->post('/api/subscribers', $payload);

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to create subscriber: " . $response->body(),
                    $response->status()
                );
            }

            return $response->json();

        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new ListmonkConnectionException(
                "Connection error while creating subscriber: " . $e->getMessage(),
                0,
                $e
            );
        } catch (\Exception $e) {
            throw new ListmonkApiException(
                "Unexpected error creating subscriber: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Update an existing subscriber in Listmonk.
     * - Updates name and attributes
     * - Merges new lists with existing lists (doesn't overwrite)
     *
     * @return array API response
     * @throws ListmonkApiException
     */
    protected function updateRemote(array $remote, NewsletterSubscriber $model): array
    {
        try {
            $remoteId = $remote['id'];
            $existingLists = $this->extractListIds($remote);
            $newLists = $model->getNewsletterLists();

            // Merge lists: keep existing + add new ones
            $mergedLists = $this->mergeLists($existingLists, $newLists);

            $nameColumn = $model->getNewsletterNameColumn();

            $payload = [
                'email' => $model->getNewsletterEmail(),
                'name' => $model->{$nameColumn} ?? '',
                'status' => 'enabled',
                'lists' => $mergedLists,
                'attribs' => $model->getNewsletterAttributes(),
                'preconfirm_subscriptions' => config('listmonk.preconfirm_subscriptions', true),
            ];

            $response = $this->client->put("/api/subscribers/{$remoteId}", $payload);

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to update subscriber: " . $response->body(),
                    $response->status()
                );
            }

            return $response->json();

        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new ListmonkConnectionException(
                "Connection error while updating subscriber: " . $e->getMessage(),
                0,
                $e
            );
        } catch (\Exception $e) {
            throw new ListmonkApiException(
                "Unexpected error updating subscriber: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Validate email address format.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateEmail(string $email): void
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email address cannot be empty.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address format: {$email}");
        }

        // Additional check: no spaces allowed
        if (str_contains($email, ' ')) {
            throw new \InvalidArgumentException("Email address cannot contain spaces: {$email}");
        }
    }

    /**
     * Extract list IDs from a remote subscriber response.
     */
    protected function extractListIds(array $remote): array
    {
        $lists = Arr::get($remote, 'lists', []);

        return collect($lists)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Merge existing lists with new lists (union operation).
     * Removes duplicates and returns a clean array.
     */
    protected function mergeLists(array $existing, array $new): array
    {
        return array_values(array_unique([
            ...$existing,
            ...$new,
        ]));
    }
}
