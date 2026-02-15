<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Http\Client\PendingRequest;
use XLaravel\Listmonk\Concerns\MakesApiCalls;

class Lists
{
    use MakesApiCalls;

    public function __construct(protected PendingRequest $client)
    {
    }

    /**
     * GET /api/lists
     */
    public function get(
        ?string $query = null,
        ?string $status = null,
        ?array $tags = null,
        string $orderBy = 'id',
        string $order = 'desc',
        int $page = 1,
        int $perPage = 20,
        bool $minimal = false,
    ): array {
        $params = array_filter([
            'query' => $query,
            'status' => $status,
            'tags' => $tags ? implode(',', $tags) : null,
            'order_by' => $orderBy,
            'order' => $order,
            'page' => $page,
            'per_page' => $perPage,
            'minimal' => $minimal ? 'true' : null,
        ], fn ($v) => $v !== null);

        return $this->apiCall(
            fn () => $this->client->get('/api/lists', $params),
            'fetch lists'
        )->json('data');
    }

    /**
     * GET /api/lists/{id}
     */
    public function find(int $id): array
    {
        return $this->apiCall(
            fn () => $this->client->get("/api/lists/{$id}"),
            'find list'
        )->json('data');
    }

    /**
     * POST /api/lists
     */
    public function create(array $data): array
    {
        return $this->apiCall(
            fn () => $this->client->post('/api/lists', $data),
            'create list'
        )->json('data');
    }

    /**
     * PUT /api/lists/{id}
     */
    public function update(int $id, array $data): array
    {
        return $this->apiCall(
            fn () => $this->client->put("/api/lists/{$id}", $data),
            'update list'
        )->json('data');
    }

    /**
     * DELETE /api/lists/{id}
     */
    public function delete(int $id): void
    {
        $this->apiCall(
            fn () => $this->client->delete("/api/lists/{$id}"),
            'delete list'
        );
    }

}
