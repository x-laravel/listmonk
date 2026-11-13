<?php

namespace XLaravel\Listmonk\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use XLaravel\Listmonk\Client;
use XLaravel\Listmonk\Contracts\HasSubscriber;
use XLaravel\Listmonk\Events\SubscriberRemoved;
use XLaravel\Listmonk\Events\SubscriberSynced;
use XLaravel\Listmonk\Models\Subscriber;

/**
 * @internal
 */
final class SubscriberSync
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function handle(string $type, int|string $key): void
    {
        $model = self::model($type, $key);
        $subscriber = ListmonkConfig::subscriberModel()::query()
            ->where('subscribable_type', $type)
            ->where('subscribable_id', $key)
            ->first();

        $wanted = $model === null ? null : self::wanted($model);

        if ($model === null || $wanted === null) {
            if ($subscriber !== null) {
                $this->remove($subscriber);
            }

            return;
        }

        $remote = $subscriber === null
            ? $this->create($wanted)
            : $this->update($this->client->subscriber($subscriber->listmonk_id), $wanted, $subscriber->lists);

        if ($remote === null || ! is_int($remote['id'] ?? null)) {
            return;
        }

        $subscriber ??= new (ListmonkConfig::subscriberModel())();
        $subscriber->subscribable()->associate($model);
        $subscriber->fill([
            'listmonk_id' => $remote['id'],
            'email' => $wanted['email'],
            'lists' => $wanted['lists'],
            'hash' => self::digest($wanted),
        ])->save();

        Event::dispatch(new SubscriberSynced($subscriber));
    }

    public function remove(Subscriber $subscriber): void
    {
        $this->detach($subscriber);

        $subscriber->delete();

        Event::dispatch(new SubscriberRemoved($subscriber));
    }

    public function detach(Subscriber $subscriber): void
    {
        $shared = $subscriber->newQuery()
            ->where('listmonk_id', $subscriber->listmonk_id)
            ->whereKeyNot($subscriber->getKey())
            ->exists();

        if (! $shared) {
            $this->client->deleteSubscriber($subscriber->listmonk_id);

            return;
        }

        if ($subscriber->lists !== []) {
            $this->client->removeFromLists($subscriber->listmonk_id, $subscriber->lists);
        }
    }

    public static function hash(Model&HasSubscriber $model): ?string
    {
        $wanted = self::wanted($model);

        return $wanted === null ? null : self::digest($wanted);
    }

    /**
     * @return array{email: string, name: string, lists: list<int>, attribs: array<string, mixed>}|null
     */
    private static function wanted(Model&HasSubscriber $model): ?array
    {
        if (! $model->exists || (method_exists($model, 'trashed') && $model->trashed())) {
            return null;
        }

        $email = mb_strtolower(trim((string) $model->subscriberEmail()));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $lists = array_values(array_unique($model->subscriberLists()));
        sort($lists);

        return [
            'email' => $email,
            'name' => trim($model->subscriberName()),
            'lists' => $lists,
            'attribs' => method_exists($model, 'subscriberAttributes') ? self::attributes($model->subscriberAttributes()) : [],
        ];
    }

    /**
     * @param  array{email: string, name: string, lists: list<int>, attribs: array<string, mixed>}  $wanted
     * @return array<string, mixed>|null
     */
    private function create(array $wanted): ?array
    {
        try {
            return $this->client->createSubscriber($this->payload($wanted, 'enabled', $wanted['lists'], $wanted['attribs']));
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 409) {
                throw $exception;
            }
        }

        return $this->update($this->client->findSubscriber($wanted['email']) ?? throw $exception, $wanted, []);
    }

    /**
     * @param  array<string, mixed>|null  $remote
     * @param  array{email: string, name: string, lists: list<int>, attribs: array<string, mixed>}  $wanted
     * @param  list<int>  $previous
     * @return array<string, mixed>|null
     */
    private function update(?array $remote, array $wanted, array $previous): ?array
    {
        if ($remote === null || ! is_int($remote['id'] ?? null)) {
            return null;
        }

        $lists = array_values(array_unique([...array_diff(self::lists($remote), $previous), ...$wanted['lists']]));
        $status = is_string($remote['status'] ?? null) ? $remote['status'] : 'enabled';
        $attribs = [...self::attributes($remote['attribs'] ?? []), ...$wanted['attribs']];

        return $this->client->updateSubscriber($remote['id'], $this->payload($wanted, $status, $lists, $attribs));
    }

    /**
     * @param  array{email: string, name: string, lists: list<int>, attribs: array<string, mixed>}  $wanted
     * @param  list<int>  $lists
     * @param  array<string, mixed>  $attribs
     * @return array<string, mixed>
     */
    private function payload(array $wanted, string $status, array $lists, array $attribs): array
    {
        return [
            'email' => $wanted['email'],
            'name' => $wanted['name'],
            'status' => $status,
            'lists' => $lists,
            'attribs' => (object) $attribs,
            'preconfirm_subscriptions' => ListmonkConfig::preconfirm(),
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return list<int>
     */
    private static function lists(array $remote): array
    {
        $lists = [];

        foreach (is_array($remote['lists'] ?? null) ? $remote['lists'] : [] as $list) {
            if (is_array($list) && is_int($list['id'] ?? null)) {
                $lists[] = $list['id'];
            }
        }

        return $lists;
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(mixed $attributes): array
    {
        $named = [];

        foreach (is_array($attributes) ? $attributes : [] as $name => $value) {
            $named[(string) $name] = $value;
        }

        return $named;
    }

    /**
     * @param  array{email: string, name: string, lists: list<int>, attribs: array<string, mixed>}  $wanted
     */
    private static function digest(array $wanted): string
    {
        return hash('sha256', (string) json_encode([$wanted, ListmonkConfig::preconfirm()]));
    }

    /**
     * @return (Model&HasSubscriber)|null
     */
    private static function model(string $type, int|string $key): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, HasSubscriber::class)) {
            return null;
        }

        $model = $class::query()->withoutGlobalScopes()->find($key);

        return $model instanceof HasSubscriber ? $model : null;
    }
}
