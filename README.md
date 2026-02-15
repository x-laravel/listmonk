# Listmonk for Laravel

A Laravel package for integrating with [Listmonk](https://listmonk.app) — the self-hosted newsletter and mailing list manager.

Provides a clean API wrapper for Listmonk's REST API, automatic model-to-subscriber synchronization, queue support, and Eloquent lifecycle hooks.

## Requirements

- PHP 8.1+
- Laravel 10 or 11
- A running Listmonk instance

## Installation

```bash
composer require xlaravel/listmonk
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=listmonk-config
```

Add the following to your `.env`:

```env
LISTMONK_BASE_URL=https://listmonk.example.com
LISTMONK_API_USER=your-api-user
LISTMONK_API_TOKEN=your-api-token
```

## Configuration

```env
# API
LISTMONK_BASE_URL=https://listmonk.example.com
LISTMONK_API_USER=
LISTMONK_API_TOKEN=

# Subscriptions
LISTMONK_PRECONFIRM_SUBSCRIPTIONS=true

# Queue
LISTMONK_QUEUE_ENABLED=true
LISTMONK_QUEUE_CONNECTION=null
LISTMONK_QUEUE_NAME=null
LISTMONK_QUEUE_DELAY=0
LISTMONK_QUEUE_TRIES=3
LISTMONK_QUEUE_BACKOFF=10,30,60

# Passive list (move deleted users here instead of unsubscribing)
LISTMONK_PASSIVE_LIST_ID=null

# What to do with old email when a user changes their email
# Options: "delete" or "passive"
LISTMONK_EMAIL_CHANGE_BEHAVIOR=delete
```

## Quick Start

### 1. Implement the interface and trait on your model

```php
use XLaravel\Listmonk\Contracts\NewsletterSubscriber;
use XLaravel\Listmonk\Traits\InteractsWithNewsletter;

class User extends Authenticatable implements NewsletterSubscriber
{
    use InteractsWithNewsletter;
}
```

That's it. The package will automatically sync your model to Listmonk on `created`, `updated`, `deleted`, and `restored` events.

### 2. Customize (optional)

Override trait methods in your model to customize behavior:

```php
class User extends Authenticatable implements NewsletterSubscriber
{
    use InteractsWithNewsletter;

    // Custom email column
    protected string $newsletterEmailColumn = 'email_address';

    // Custom name column
    public function getNewsletterNameColumn(): string
    {
        return 'full_name';
    }

    // Custom list IDs
    public function getNewsletterLists(): array
    {
        $lists = [1]; // Main newsletter

        if ($this->is_premium) {
            $lists[] = 2; // Premium list
        }

        return $lists;
    }

    // Custom attributes synced to Listmonk
    public function getNewsletterAttributes(): array
    {
        return [
            'plan' => $this->subscription_plan ?? '',
            'country' => $this->country ?? '',
            'registered_at' => $this->created_at?->toIso8601String(),
        ];
    }

    // Custom passive list per model
    public function getNewsletterPassiveListId(): ?int
    {
        return 5;
    }
}
```

## API Usage

The package exposes three services through the `Listmonk` facade:

### Subscribers API (raw wrapper)

```php
use XLaravel\Listmonk\Facades\Listmonk;

// List subscribers
Listmonk::subscribers()->get(query: "subscribers.email LIKE '%@example.com%'");

// Find by ID
Listmonk::subscribers()->find(42);

// Create
Listmonk::subscribers()->create([
    'email' => 'user@example.com',
    'name' => 'John Doe',
    'lists' => [1, 2],
    'status' => 'enabled',
    'preconfirm_subscriptions' => true,
]);

// Update
Listmonk::subscribers()->update(42, [
    'name' => 'Jane Doe',
    'lists' => [1, 2, 3],
]);

// Delete
Listmonk::subscribers()->delete(42);
Listmonk::subscribers()->deleteMany([42, 43, 44]);

// Manage lists
Listmonk::subscribers()->updateList(
    subscriberIds: [42, 43],
    listIds: [1, 2],
    action: 'add' // 'add', 'remove', or 'unsubscribe'
);

// Other operations
Listmonk::subscribers()->blocklist(42);
Listmonk::subscribers()->export(42);
Listmonk::subscribers()->bounces(42);
Listmonk::subscribers()->deleteBounces(42);
Listmonk::subscribers()->sendOptin(42);
```

### Lists API (raw wrapper)

```php
Listmonk::lists()->get();
Listmonk::lists()->get(query: 'newsletter', status: 'enabled', minimal: true);
Listmonk::lists()->find(1);
Listmonk::lists()->create(['name' => 'My List', 'type' => 'public']);
Listmonk::lists()->update(1, ['name' => 'Updated List']);
Listmonk::lists()->delete(1);
```

### Newsletter Manager (business logic)

```php
// Sync a model (create or update in Listmonk)
Listmonk::newsletter()->sync($user);

// Partial update (only specified fields)
Listmonk::newsletter()->updatePartial($user, ['name']);

// Unsubscribe (delete from Listmonk)
Listmonk::newsletter()->unsubscribe($user);
Listmonk::newsletter()->unsubscribeByEmail('old@example.com');

// Move to passive list
Listmonk::newsletter()->moveToPassiveList($user, passiveListId: 5);
Listmonk::newsletter()->moveToPassiveListByEmail('old@example.com', passiveListId: 5);

// Batch sync
$results = Listmonk::newsletter()->syncMany(User::all());
// ['synced' => 95, 'failed' => 5, 'errors' => [...]]
```

### Helper function

```php
listmonk()->subscribers()->find(42);
listmonk()->newsletter()->sync($user);
```

## Model Actions

Models using the `InteractsWithNewsletter` trait get these methods:

```php
$user->subscribeToNewsletter();
$user->unsubscribeFromNewsletter();
$user->updateNewsletterSubscription();
$user->moveToPassiveList();
```

All respect the `LISTMONK_QUEUE_ENABLED` setting — when enabled, operations are dispatched as queued jobs.

### Temporarily disable sync

```php
User::withoutNewsletterSync(function () {
    $user->update(['email' => 'new@example.com']);
    // No Listmonk API calls will be made
});
```

## Events

| Event | When |
|-------|------|
| `SubscriberSubscribed` | New subscriber created in Listmonk |
| `SubscriberSynced` | Existing subscriber updated in Listmonk |
| `SubscriberUnsubscribed` | Subscriber deleted from Listmonk |
| `SubscriberSyncFailed` | Sync failed (with exception) |

```php
use XLaravel\Listmonk\Events\SubscriberSubscribed;

class SendWelcomeEmail
{
    public function handle(SubscriberSubscribed $event): void
    {
        $model = $event->model;
        $apiResponse = $event->response;
    }
}
```

## Artisan Commands

```bash
# Check API connectivity
php artisan listmonk:health

# Sync all subscribers
php artisan listmonk:sync

# Sync a specific model
php artisan listmonk:sync "App\Models\Customer"

# With options
php artisan listmonk:sync --chunk=200 --force --dry-run
```

## Testing

The package provides a test trait for faking API calls:

```php
use XLaravel\Listmonk\Testing\InteractsWithListmonk;

class MyTest extends TestCase
{
    use InteractsWithListmonk;

    public function test_user_subscribes(): void
    {
        $this->fakeListmonk();

        $user = User::factory()->create();

        $this->assertSubscriberSynced($user->email);
    }

    public function test_with_existing_subscriber(): void
    {
        $this->fakeListmonkWithSubscriber([
            'id' => 1,
            'email' => 'user@example.com',
            'name' => 'John',
            'lists' => [['id' => 1]],
            'attribs' => [],
            'status' => 'enabled',
        ]);

        // ...
    }

    public function test_api_failure(): void
    {
        $this->fakeListmonkFailure(500, 'Internal Server Error');

        // ...
    }
}
```

## Architecture

```
src/
├── Concerns/
│   ├── MakesApiCalls.php         # Shared API error handling
│   └── ConfiguresQueue.php       # Shared job queue configuration
├── Contracts/
│   └── NewsletterSubscriber.php  # Interface for syncable models
├── Console/
│   ├── ListmonkHealthCommand.php
│   └── SyncSubscribersCommand.php
├── Events/
│   ├── SubscriberSubscribed.php
│   ├── SubscriberSynced.php
│   ├── SubscriberSyncFailed.php
│   └── SubscriberUnsubscribed.php
├── Exceptions/
│   ├── ListmonkException.php
│   ├── ListmonkApiException.php
│   └── ListmonkConnectionException.php
├── Facades/
│   └── Listmonk.php
├── Jobs/
│   ├── SubscribeJob.php
│   ├── UnsubscribeJob.php
│   ├── UnsubscribeJobByEmail.php
│   ├── UpdateSubscriptionJob.php
│   └── MoveToPassiveListJobByEmail.php
├── Observers/
│   └── NewsletterSubscriberObserver.php
├── Services/
│   ├── Subscribers.php           # Listmonk Subscribers API wrapper
│   ├── Lists.php                 # Listmonk Lists API wrapper
│   └── NewsletterManager.php     # Business logic (sync, merge, events)
├── Testing/
│   └── InteractsWithListmonk.php
├── Traits/
│   └── InteractsWithNewsletter.php
├── Listmonk.php                  # Main service
├── ListmonkServiceProvider.php
└── helpers.php
```

## License

MIT
