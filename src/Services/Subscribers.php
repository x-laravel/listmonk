<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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
    public function __construct(
        protected PendingRequest $client
    )
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
            $this->checkRateLimit();

            $email = $model->getNewsletterEmail();

            // Validate email format
            $this->validateEmail($email);

            Log::info('Starting Listmonk sync', [
                'email' => $email,
                'model' => get_class($model),
                'lists' => $model->getNewsletterLists()
            ]);

            // Fetch subscriber from Listmonk by email
            $remote = $this->fetchRemoteByEmail($email);

            if ($remote) {
                // Subscriber exists - update it
                $response = $this->updateRemote($remote, $model);
                Log::info('Subscriber updated in Listmonk', [
                    'email' => $email,
                    'listmonk_id' => $remote['id']
                ]);

                event(new SubscriberSynced($model, $response));
            } else {
                // Subscriber doesn't exist - create it
                $response = $this->createRemote($model);
                Log::info('Subscriber created in Listmonk', [
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
     * Silently succeeds if subscriber doesn't exist (idempotent).
     */
    public function unsubscribe(NewsletterSubscriber $model): void
    {
        try {
            $email = $model->getNewsletterEmail();

            // Validate email format
            $this->validateEmail($email);

            Log::info('Attempting to unsubscribe from Listmonk', [
                'email' => $email,
                'model' => get_class($model)
            ]);

            $remote = $this->fetchRemoteByEmail($email);

            if (!$remote) {
                // Subscriber doesn't exist in Listmonk - this is OK, just log and return
                Log::info('Subscriber not found in Listmonk for unsubscribe (already unsubscribed or never subscribed)', [
                    'email' => $email
                ]);
                return;
            }

            // Set status to disabled
            $response = $this->client->put("/api/subscribers/{$remote['id']}", [
                'status' => 'disabled'
            ]);

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to unsubscribe subscriber: " . $response->body(),
                    $response->status()
                );
            }

            Log::info('Subscriber unsubscribed from Listmonk', [
                'email' => $email,
                'listmonk_id' => $remote['id']
            ]);

            event(new SubscriberUnsubscribed($model));

        } catch (ListmonkApiException $e) {
            Log::error('Listmonk unsubscribe failed', [
                'email' => $model->getNewsletterEmail(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during unsubscribe', [
                'email' => $model->getNewsletterEmail(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
    protected function fetchRemoteByEmail(string $email): ?array
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
            $payload = [
                'email' => $model->getNewsletterEmail(),
                'name' => $model->getNewsletterName(),
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

            $payload = [
                'email' => $model->getNewsletterEmail(),
                'name' => $model->getNewsletterName(),
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
    | RATE LIMITING
    |--------------------------------------------------------------------------
    */

    /**
     * Check if rate limit is exceeded.
     *
     * @throws ListmonkApiException
     */
    protected function checkRateLimit(): void
    {
        if (!config('listmonk.rate_limit.enabled', false)) {
            return;
        }

        $key = 'listmonk:rate_limit';
        $maxAttempts = config('listmonk.rate_limit.max_attempts', 60);
        $decayMinutes = config('listmonk.rate_limit.decay_minutes', 1);

        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            throw new ListmonkApiException(
                "Rate limit exceeded. Maximum {$maxAttempts} requests per {$decayMinutes} minute(s)."
            );
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));
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
     * Generate an idempotency key for a subscriber operation.
     * This can be used to prevent duplicate operations.
     *
     * @return string
     */
    protected function getIdempotencyKey(NewsletterSubscriber $model): string
    {
        return hash('sha256', implode('|', [
            $model->getNewsletterEmail(),
            get_class($model),
            date('Y-m-d') // Daily idempotency
        ]));
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
