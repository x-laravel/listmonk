<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Http\Client\PendingRequest;
use XLaravel\Listmonk\Concerns\MakesApiCalls;

class Subscribers
{
    use MakesApiCalls;

    public function __construct(protected PendingRequest $client)
    {
    }

    /**
     * GET /api/subscribers
     */
    public function get(
        ?string $query = null,
        ?int $listId = null,
        ?string $subscriptionStatus = null,
        string $orderBy = 'id',
        string $order = 'desc',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $params = array_filter([
            'query' => $query,
            'list_id' => $listId,
            'subscription_status' => $subscriptionStatus,
            'order_by' => $orderBy,
            'order' => $order,
            'page' => $page,
            'per_page' => $perPage,
        ], fn ($v) => $v !== null);

        return $this->apiCall(
            fn () => $this->client->get('/api/subscribers', $params),
            'fetch subscribers'
        )->json('data');
    }

    /**
     * GET /api/subscribers/{id}
     */
    public function find(int $id): array
    {
        return $this->apiCall(
            fn () => $this->client->get("/api/subscribers/{$id}"),
            'find subscriber'
        )->json('data');
    }

    /**
     * POST /api/subscribers
     */
    public function create(array $data): array
    {
        return $this->apiCall(
            fn () => $this->client->post('/api/subscribers', $data),
            'create subscriber'
        )->json('data');
    }

    /**
     * PUT /api/subscribers/{id}
     */
    public function update(int $id, array $data): array
    {
        return $this->apiCall(
            fn () => $this->client->put("/api/subscribers/{$id}", $data),
            'update subscriber'
        )->json('data');
    }

    /**
     * DELETE /api/subscribers/{id}
     */
    public function delete(int $id): void
    {
        $this->apiCall(
            fn () => $this->client->delete("/api/subscribers/{$id}"),
            'delete subscriber'
        );
    }

    /**
     * DELETE /api/subscribers (multiple)
     */
    public function deleteMany(array $ids): void
    {
        $this->apiCall(
            fn () => $this->client->delete('/api/subscribers', ['ids' => $ids]),
            'delete subscribers'
        );
    }

    /**
     * PUT /api/subscribers/lists
     */
    public function updateList(array $subscriberIds, array $listIds, string $action = 'add'): void
    {
        $this->apiCall(
            fn () => $this->client->put('/api/subscribers/lists', [
                'ids' => $subscriberIds,
                'lists' => $listIds,
                'action' => $action,
            ]),
            'update subscriber lists'
        );
    }

    /**
     * PUT /api/subscribers/{id}/blocklist
     */
    public function blocklist(int $id): void
    {
        $this->apiCall(
            fn () => $this->client->put("/api/subscribers/{$id}/blocklist"),
            'blocklist subscriber'
        );
    }

    /**
     * GET /api/subscribers/{id}/export
     */
    public function export(int $id): array
    {
        return $this->apiCall(
            fn () => $this->client->get("/api/subscribers/{$id}/export"),
            'export subscriber'
        )->json('data');
    }

    /**
     * GET /api/subscribers/{id}/bounces
     */
    public function bounces(int $id): array
    {
        return $this->apiCall(
            fn () => $this->client->get("/api/subscribers/{$id}/bounces"),
            'fetch subscriber bounces'
        )->json('data');
    }

    /**
     * DELETE /api/subscribers/{id}/bounces
     */
    public function deleteBounces(int $id): void
    {
        $this->apiCall(
            fn () => $this->client->delete("/api/subscribers/{$id}/bounces"),
            'delete subscriber bounces'
        );
    }

    /**
     * POST /api/subscribers/{id}/optin
     */
    public function sendOptin(int $id): void
    {
        $this->apiCall(
            fn () => $this->client->post("/api/subscribers/{$id}/optin"),
            'send optin'
        );
    }
}
