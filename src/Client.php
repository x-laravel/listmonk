<?php

namespace XLaravel\Listmonk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use XLaravel\Listmonk\Support\ListmonkConfig;

class Client
{
    /**
     * @return array<string, mixed>|null
     */
    public function subscriber(int $id): ?array
    {
        $response = $this->request()->get("/api/subscribers/{$id}");

        if ($this->missing($response)) {
            return null;
        }

        return $this->data($response->throw());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSubscriber(string $email): ?array
    {
        $email = mb_strtolower(trim($email));

        $results = $this->request()->throw()
            ->get('/api/subscribers', ['search' => '^' . preg_quote($email) . '$', 'per_page' => 10])
            ->json('data.results');

        foreach (is_array($results) ? $results : [] as $subscriber) {
            if (is_array($subscriber) && is_string($subscriber['email'] ?? null) && mb_strtolower($subscriber['email']) === $email) {
                /** @var array<string, mixed> $subscriber */
                return $subscriber;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $subscriber
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createSubscriber(array $subscriber): array
    {
        return $this->data($this->request()->throw()->post('/api/subscribers', $subscriber));
    }

    /**
     * @param  array<string, mixed>  $subscriber
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function updateSubscriber(int $id, array $subscriber): array
    {
        return $this->data($this->request()->throw()->put("/api/subscribers/{$id}", $subscriber));
    }

    public function deleteSubscriber(int $id): void
    {
        $response = $this->request()->delete("/api/subscribers/{$id}");

        if (! $this->missing($response)) {
            $response->throw();
        }
    }

    /**
     * @param  list<int>  $lists
     */
    public function removeFromLists(int $id, array $lists): void
    {
        $this->request()->throw()->put('/api/subscribers/lists', [
            'ids' => [$id],
            'action' => 'remove',
            'target_list_ids' => $lists,
        ]);
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(ListmonkConfig::url())
            ->withHeaders(['Authorization' => 'token ' . ListmonkConfig::username() . ':' . ListmonkConfig::token()])
            ->timeout(ListmonkConfig::timeout())
            ->acceptJson()
            ->asJson();
    }

    protected function missing(Response $response): bool
    {
        return in_array($response->status(), [400, 404], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(Response $response): array
    {
        $data = $response->json('data');

        /** @var array<string, mixed> */
        return is_array($data) ? $data : [];
    }
}
