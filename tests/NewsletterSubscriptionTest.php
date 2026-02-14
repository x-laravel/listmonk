<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use XLaravel\Listmonk\Testing\InteractsWithListmonk;
use XLaravel\Listmonk\Exceptions\ListmonkApiException;
use XLaravel\Listmonk\Events\SubscriberSubscribed;
use XLaravel\Listmonk\Events\SubscriberSynced;
use XLaravel\Listmonk\Events\SubscriberSyncFailed;
use Illuminate\Support\Facades\Event;

class NewsletterSubscriptionTest extends TestCase
{
    use InteractsWithListmonk;

    public function test_user_can_subscribe_to_newsletter()
    {
        $this->fakeListmonk();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        $user->subscribeToNewsletter();

        $this->assertSubscriberSynced('test@example.com');
    }

    public function test_subscriber_subscribed_event_is_dispatched_for_new_subscriber()
    {
        Event::fake();
        $this->fakeListmonk();

        $user = User::factory()->create();
        $user->subscribeToNewsletter();

        Event::assertDispatched(SubscriberSubscribed::class, function ($event) use ($user) {
            return $event->model->id === $user->id;
        });

        Event::assertNotDispatched(SubscriberSynced::class);
    }

    public function test_subscriber_synced_event_is_dispatched_for_existing_subscriber()
    {
        Event::fake();

        $this->fakeListmonkWithSubscriber([
            'id' => 123,
            'email' => 'existing@example.com',
            'name' => 'Old Name',
            'lists' => [['id' => 1]]
        ]);

        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'New Name'
        ]);

        $user->subscribeToNewsletter();

        Event::assertDispatched(SubscriberSynced::class, function ($event) use ($user) {
            return $event->model->id === $user->id;
        });

        Event::assertNotDispatched(SubscriberSubscribed::class);
    }

    public function test_sync_failed_event_is_dispatched_on_error()
    {
        Event::fake();
        $this->fakeListmonkFailure(500);

        $user = User::factory()->create();

        try {
            $user->subscribeToNewsletter();
        } catch (ListmonkApiException $e) {
            // Expected
        }

        Event::assertDispatched(SubscriberSyncFailed::class);
    }

    public function test_handles_api_connection_failure()
    {
        $this->fakeListmonkFailure(503, 'Service Unavailable');

        $user = User::factory()->create();

        $this->expectException(ListmonkApiException::class);
        $user->subscribeToNewsletter();
    }

    public function test_user_can_unsubscribe()
    {
        $this->fakeListmonkWithSubscriber([
            'id' => 123,
            'email' => 'test@example.com',
        ]);

        $user = User::factory()->create(['email' => 'test@example.com']);
        $user->unsubscribeFromNewsletter();

        $this->assertListmonkCalled('/api/subscribers');
    }
}
