<?php

namespace XLaravel\Listmonk\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as BaseTestCase;
use XLaravel\Listmonk\ListmonkServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array<int, array{id: int, email: string, name: string, status: string, attribs: array<string, mixed>, lists: array<int, string>}>
     */
    protected array $remote = [];

    /**
     * @var list<string>
     */
    protected array $requests = [];

    protected int $nextId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->respond($request));
    }

    protected function getPackageProviders($app): array
    {
        return [ListmonkServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $connection = getenv('DB_CONNECTION');

        $app['config']->set('database.default', is_string($connection) && $connection !== '' ? $connection : 'testing');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('listmonk.url', 'https://listmonk.test');
        $app['config']->set('listmonk.username', 'api');
        $app['config']->set('listmonk.token', 'secret');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * @param  array<int, string>  $lists
     * @param  array<string, mixed>  $attribs
     */
    protected function remoteSubscriber(string $email, array $lists = [], string $status = 'enabled', array $attribs = []): int
    {
        $id = $this->nextId++;

        $this->remote[$id] = ['id' => $id, 'email' => $email, 'name' => '', 'status' => $status, 'attribs' => $attribs, 'lists' => $lists];

        return $id;
    }

    protected function respond(Request $request): mixed
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $method = $request->method();
        $this->requests[] = "{$method} {$path}";
        $data = $request->data();
        $id = (int) basename($path);

        if ($method === 'POST' && $path === '/api/subscribers') {
            foreach ($this->remote as $subscriber) {
                if ($subscriber['email'] === $data['email']) {
                    return Http::response(['message' => 'E-mail already exists.'], 409);
                }
            }

            $id = $this->remoteSubscriber($this->text($data['email'] ?? null));

            return $this->store($id, $data);
        }

        if ($method === 'PUT' && $path === '/api/subscribers/lists') {
            foreach ($this->integers($data['ids'] ?? null) as $subscriber) {
                foreach ($this->integers($data['target_list_ids'] ?? null) as $list) {
                    unset($this->remote[$subscriber]['lists'][$list]);
                }
            }

            return Http::response(['data' => true]);
        }

        if ($method === 'GET' && $path === '/api/subscribers') {
            $pattern = '/' . $this->text($data['search'] ?? null) . '/i';

            return Http::response(['data' => ['results' => array_values(array_map(
                $this->present(...),
                array_filter($this->remote, fn (array $subscriber) => preg_match($pattern, $subscriber['email']) === 1),
            ))]]);
        }

        if (! isset($this->remote[$id])) {
            return Http::response(['message' => 'Subscriber not found.'], 400);
        }

        if ($method === 'DELETE') {
            unset($this->remote[$id]);

            return Http::response(['data' => true]);
        }

        return $method === 'PUT' ? $this->store($id, $data) : Http::response(['data' => $this->present($this->remote[$id])]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function store(int $id, array $data): mixed
    {
        $lists = [];

        foreach ($this->integers($data['lists'] ?? null) as $list) {
            $lists[$list] = $this->remote[$id]['lists'][$list] ?? (($data['preconfirm_subscriptions'] ?? false) === true ? 'confirmed' : 'unconfirmed');
        }

        $attribs = [];

        foreach ((array) ($data['attribs'] ?? []) as $name => $value) {
            $attribs[(string) $name] = $value;
        }

        $this->remote[$id] = [
            'id' => $id,
            'email' => $this->text($data['email'] ?? null),
            'name' => $this->text($data['name'] ?? null),
            'status' => $this->text($data['status'] ?? null),
            'attribs' => $attribs,
            'lists' => $lists,
        ];

        return Http::response(['data' => $this->present($this->remote[$id])]);
    }

    /**
     * @param  array{id: int, email: string, name: string, status: string, attribs: array<string, mixed>, lists: array<int, string>}  $subscriber
     * @return array<string, mixed>
     */
    private function present(array $subscriber): array
    {
        $lists = [];

        foreach ($subscriber['lists'] as $list => $status) {
            $lists[] = ['id' => $list, 'subscription_status' => $status];
        }

        return [...$subscriber, 'attribs' => (object) $subscriber['attribs'], 'lists' => $lists];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<int>
     */
    private function integers(mixed $values): array
    {
        return array_values(array_filter(is_array($values) ? $values : [], is_int(...)));
    }
}
