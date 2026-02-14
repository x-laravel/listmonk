<?php

namespace XLaravel\Listmonk\Testing;

use Illuminate\Support\Facades\Http;

trait InteractsWithListmonk
{
    /**
     * Fake all Listmonk API calls.
     */
    protected function fakeListmonk(): void
    {
        Http::fake([
            '*/api/subscribers*' => Http::response([
                'data' => [
                    'results' => [],
                    'total' => 0,
                    'per_page' => 20,
                    'page' => 1
                ]
            ], 200),

            '*/api/health' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    /**
     * Fake Listmonk with a specific subscriber.
     */
    protected function fakeListmonkWithSubscriber(array $subscriber): void
    {
        Http::fake([
            '*/api/subscribers*' => Http::response([
                'data' => [
                    'results' => [$subscriber],
                    'total' => 1,
                    'per_page' => 20,
                    'page' => 1
                ]
            ], 200),

            '*/api/health' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    /**
     * Fake Listmonk API failure.
     */
    protected function fakeListmonkFailure(int $statusCode = 500, string $message = 'Server Error'): void
    {
        Http::fake([
            '*/api/*' => Http::response([
                'message' => $message
            ], $statusCode),
        ]);
    }

    /**
     * Assert Listmonk API was called.
     */
    protected function assertListmonkCalled(string $endpoint): void
    {
        Http::assertSent(function ($request) use ($endpoint) {
            return str_contains($request->url(), $endpoint);
        });
    }

    /**
     * Assert subscriber was synced.
     */
    protected function assertSubscriberSynced(string $email): void
    {
        Http::assertSent(function ($request) use ($email) {
            $body = $request->data();
            return isset($body['email']) && $body['email'] === $email;
        });
    }
}
