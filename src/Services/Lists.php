<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use XLaravel\Listmonk\Exceptions\ListmonkApiException;
use XLaravel\Listmonk\Exceptions\ListmonkConnectionException;

class Lists
{
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

    /**
     * Execute an API call with unified error handling.
     *
     * @throws ListmonkApiException
     * @throws ListmonkConnectionException
     */
    protected function apiCall(\Closure $request, string $operation): Response
    {
        try {
            $response = $request();

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to {$operation}: " . $response->body(),
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
}
