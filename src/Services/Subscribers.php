<?php

namespace XLaravel\Listmonk\Services;

use Illuminate\Support\Arr;
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Http\Client;
use XLaravel\Listmonk\Models\ListmonkSubscriber;

class Subscribers
{
    public function __construct(
        protected Client $client
    ) {}

    public function sync(NewsletterSubscriber $model): void
    {
        $local = $this->getLocalRecord($model);

        // 1️⃣ Eğer local kayıt varsa direkt update
        if ($local && $local->listmonk_id) {
            $remote = [
                'id'    => $local->listmonk_id,
                'lists' => $local->lists ?? [],
            ];

            $response = $this->update($remote, $model);
            $this->updateLocalRecord($local, $response, $model);

            return;
        }

        // 2️⃣ Local yoksa email ile remote kontrol
        $remote = $this->findByEmail($model->getNewsletterEmail());

        if ($remote) {
            $response = $this->update($remote, $model);
        } else {
            $response = $this->create($model);
        }

        $this->storeLocally($model, $response);
    }

    protected function create(NewsletterSubscriber $model): array
    {
        $payload = $this->buildPayload($model);

        $response = $this->client->request(
            'POST',
            '/api/subscribers',
            $payload
        );

        if (!$response->successful()) {
            throw new \Exception('Listmonk create failed: ' . $response->body());
        }

        return $response->json();
    }

    protected function update(array $remote, NewsletterSubscriber $model): array
    {
        $mergedLists = $this->mergeLists(
            $remote['lists'] ?? [],
            $model->getNewsletterLists()
        );

        $payload = $this->buildPayload($model);
        $payload['lists'] = $mergedLists;

        $response = $this->client->request(
            'PUT',
            "/api/subscribers/{$remote['id']}",
            $payload
        );

        if (!$response->successful()) {
            throw new \Exception('Listmonk update failed: ' . $response->body());
        }

        return $response->json();
    }

    protected function findByEmail(string $email): ?array
    {
        $response = $this->client->request(
            'GET',
            '/api/subscribers',
            ['query' => ['email' => $email]]
        );

        if (!$response->successful()) {
            return null;
        }

        return $response->json('data.results.0');
    }

    protected function buildPayload(NewsletterSubscriber $model): array
    {
        return [
            'email'      => $model->getNewsletterEmail(),
            'name'       => $model->getNewsletterData()['name'] ?? null,
            'attributes' => $model->getNewsletterData(),
            'lists'      => $model->getNewsletterLists(),
            'status'     => 'enabled',
        ];
    }

    protected function mergeLists(array $existing, array $new): array
    {
        return array_values(array_unique([
            ...$existing,
            ...$new,
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | LOCAL DATABASE OPERATIONS
    |--------------------------------------------------------------------------
    */

    protected function getLocalRecord(NewsletterSubscriber $model): ?ListmonkSubscriber
    {
        return $model->listmonkSubscriber()->first();
    }

    protected function storeLocally(
        NewsletterSubscriber $model,
        array $remoteResponse
    ): void {
        $remoteId = Arr::get($remoteResponse, 'data.id');

        if (!$remoteId) {
            return;
        }

        $model->listmonkSubscriber()->updateOrCreate(
            [],
            [
                'listmonk_id' => $remoteId,
                'email'       => $model->getNewsletterEmail(),
                'lists'       => $model->getNewsletterLists(),
            ]
        );
    }

    protected function updateLocalRecord(
        ListmonkSubscriber $local,
        array $remoteResponse,
        NewsletterSubscriber $model
    ): void {
        $local->update([
            'listmonk_id' => Arr::get($remoteResponse, 'data.id'),
            'email'       => $model->getNewsletterEmail(),
            'lists'       => $model->getNewsletterLists(),
        ]);
    }

    public function unsubscribe(NewsletterSubscriber $model): void
    {
        $local = $this->getLocalRecord($model);

        if (!$local || !$local->listmonk_id) {
            return;
        }

        $this->client->request(
            'PUT',
            "/api/subscribers/{$local->listmonk_id}",
            ['status' => 'disabled']
        );
    }
}
