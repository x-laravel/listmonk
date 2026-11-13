# Listmonk for Laravel

<p align="center">
<a href="https://github.com/X-Laravel/listmonk/actions/workflows/tests.yml"><img src="https://github.com/X-Laravel/listmonk/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
<a href="https://packagist.org/packages/x-laravel/listmonk" rel="nofollow"><img src="https://img.shields.io/packagist/v/x-laravel/listmonk" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/x-laravel/listmonk" rel="nofollow"><img src="https://img.shields.io/packagist/php-v/x-laravel/listmonk" alt="PHP Version"></a>
<a href="https://packagist.org/packages/x-laravel/listmonk" rel="nofollow"><img src="https://img.shields.io/packagist/dt/x-laravel/listmonk" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/x-laravel/listmonk" rel="nofollow"><img src="https://img.shields.io/packagist/l/x-laravel/listmonk" alt="License"></a>
</p>

[Listmonk](https://listmonk.app) subscribers for Eloquent. A model names its e-mail address and its
lists; the package keeps the matching Listmonk subscriber up to date from a queued job whenever the
model is saved, deleted or restored.

## Requirements

| Package version | PHP | Laravel |
|-----------------|-----|---------|
| 1.x | 8.4+ | 13 |

A Listmonk API user with the `subscribers:get` and `subscribers:manage` permissions, and manage
access to the lists the models use.

## Installation

```bash
composer require x-laravel/listmonk
php artisan vendor:publish --tag=listmonk-migrations
php artisan migrate
```

Set the API user in `.env`:

```dotenv
LISTMONK_API_URL=https://listmonk.example.com
LISTMONK_API_USERNAME=my-app
LISTMONK_API_TOKEN=
```

Publish the config to change the defaults:

```bash
php artisan vendor:publish --tag=listmonk-config
```

## Preparing the Models

Implement `HasSubscriber` and use `Subscribable` on every model that is a subscriber:

```php
use XLaravel\Listmonk\Concerns\Subscribable;
use XLaravel\Listmonk\Contracts\HasSubscriber;

class User extends Authenticatable implements HasSubscriber
{
    use Subscribable;

    public function subscriberEmail(): ?string
    {
        return $this->email;                 // null keeps the model out of Listmonk
    }

    public function subscriberName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function subscriberLists(): array
    {
        return $this->wants_newsletter ? [3, 4] : [4];   // Listmonk list ids
    }

    public function subscriberAttributes(): array        // optional
    {
        return ['city' => $this->city?->name];
    }
}
```

The `subscribable_id` column follows Laravel's morph key type, so set
`Schema::defaultMorphKeyType('uuid')` or `'ulid'` before migrating if your models use them. If the
models mix key types, such as integer and ULID keys, publish the migration and make
`subscribable_id` a string column.

## Configuration

| Key | Default | Meaning |
|-----|---------|---------|
| `enabled` | `env('LISTMONK_ENABLED', true)` | Sync models; nothing is sent while false, or while the url or the token is empty |
| `url` | `env('LISTMONK_API_URL')` | Address of the Listmonk instance |
| `username` | `env('LISTMONK_API_USERNAME')` | Name of the API user |
| `token` | `env('LISTMONK_API_TOKEN')` | Token of the API user |
| `timeout` | `10` | Seconds to wait for a response |
| `preconfirm` | `true` | Add subscribers to their lists as confirmed, so no opt-in e-mail is sent |
| `queue.connection` | `null` | Queue connection of the sync jobs |
| `queue.name` | `null` | Queue of the sync jobs |
| `connection` | `null` | Connection of the subscriber table |
| `subscriber.table` | `'listmonk_subscribers'` | Name of the subscriber table |
| `subscriber.model` | `Subscriber::class` | Subscriber model; must extend the package's |
| `prune.enabled` | `true` | Schedule the pruning of left-behind subscribers |
| `prune.schedule` | `'daily'` | A cron expression, or a frequency method with its arguments: `'dailyAt:03:30'`, `'twiceDaily:1,13'`, `'weeklyOn:1,04:00'` |
| `prune.chunk` | `1000` | Rows removed by each pruning query |

## Syncing

Saving a model queues a sync once the surrounding transaction commits. A sync is queued only when
the e-mail, the name, the lists or the attributes changed, so saves of other columns send nothing.

```php
$user->update(['email' => 'new@example.com']);   // the same subscriber gets the new address
$user->update(['wants_newsletter' => false]);    // leaves list 3, stays on list 4

$user->subscriber;         // the local record: listmonk_id, email, lists
$user->syncSubscriber();   // queue a sync by hand
```

The package only touches what the model owns. Lists the subscriber joined elsewhere, attributes set
elsewhere, a blocklisted status and a list the subscriber unsubscribed from are all kept. When
Listmonk already has the e-mail, for example from a public form, that subscriber is adopted instead
of being created again.

Models with the same e-mail share one subscriber, each adding its own lists to it.

A subscriber deleted in Listmonk is not created again by later changes of the model.

Queue a sync for the records that existed before the package was installed:

```bash
php artisan listmonk:sync "App\Models\User" "App\Models\Organizer"
```

Check that Listmonk answers and accepts the API user:

```bash
php artisan listmonk:health
```

## Deleting and Pruning

When a model is deleted, soft deleted, or its `subscriberEmail()` becomes null, its subscriber is
deleted from Listmonk. A restored model is subscribed again. A subscriber shared with another model
only leaves the lists of the deleted one.

Deletes that skip model events, such as bulk queries, database cascades or raw SQL, leave
subscribers behind. The package schedules `model:prune`, daily by default, to remove subscribers
whose model no longer exists. Run it by hand with:

```bash
php artisan model:prune --model="XLaravel\Listmonk\Models\Subscriber"
```

Pruning skips types that no longer resolve to a model and models on another connection.

## Reacting to Syncs

`XLaravel\Listmonk\Events\SubscriberSynced` is dispatched after a subscriber is created or updated
in Listmonk and `SubscriberRemoved` after it is removed. Both carry the `Subscriber` record.

Subscribers removed by pruning dispatch nothing.

## Calling Listmonk

`XLaravel\Listmonk\Client` wraps the subscriber endpoints the package uses and can be resolved from
the container:

```php
$client = app(XLaravel\Listmonk\Client::class);

$client->findSubscriber('ali@example.com');   // null when Listmonk has no such e-mail
$client->subscriber(42);
```

## Testing

```bash
composer install
composer test
composer analyse
```

## License

Open source software licensed under the [MIT license](LICENSE).
