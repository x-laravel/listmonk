<?php

namespace XLaravel\Listmonk\Tests;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\Listmonk\Events\SubscriberRemoved;
use XLaravel\Listmonk\Events\SubscriberSynced;
use XLaravel\Listmonk\Tests\Fixtures\User;

class EventsTest extends TestCase
{
    #[Test]
    public function a_sync_dispatches_subscriber_synced(): void
    {
        Event::fake([SubscriberSynced::class]);

        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        $user->update(['name' => 'Veli']);
        $user->update(['logins' => 3]);

        Event::assertDispatchedTimes(SubscriberSynced::class, 2);
        Event::assertDispatched(SubscriberSynced::class, fn (SubscriberSynced $event) => $event->subscriber->subscribable?->is($user) === true);
    }

    #[Test]
    public function a_removal_dispatches_subscriber_removed(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        Event::fake([SubscriberRemoved::class]);

        $user->delete();

        Event::assertDispatchedTimes(SubscriberRemoved::class, 1);
        Event::assertDispatched(SubscriberRemoved::class, fn (SubscriberRemoved $event) => $event->subscriber->email === 'ali@example.com');
    }
}
