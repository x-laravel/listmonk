<?php

namespace XLaravel\Listmonk\Tests;

use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\Listmonk\Models\Subscriber;
use XLaravel\Listmonk\Tests\Fixtures\Guest;
use XLaravel\Listmonk\Tests\Fixtures\Organizer;
use XLaravel\Listmonk\Tests\Fixtures\User;

class SubscribableTest extends TestCase
{
    #[Test]
    public function a_created_model_becomes_a_confirmed_subscriber_of_its_lists(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'Ali@Example.com', 'city' => 'İzmir']);

        $this->assertSame(['POST /api/subscribers'], $this->requests);
        $this->assertSame([
            'id' => 1,
            'email' => 'ali@example.com',
            'name' => 'Ali',
            'status' => 'enabled',
            'attribs' => ['city' => 'İzmir'],
            'lists' => [User::ANNOUNCEMENTS => 'confirmed', User::NEWSLETTER => 'confirmed'],
        ], $this->remote[1]);

        $subscriber = $user->subscriber;
        $this->assertNotNull($subscriber);
        $this->assertSame(1, $subscriber->listmonk_id);
        $this->assertSame('ali@example.com', $subscriber->email);
        $this->assertSame([User::ANNOUNCEMENTS, User::NEWSLETTER], $subscriber->lists);
    }

    #[Test]
    public function lists_are_left_unconfirmed_when_preconfirming_is_off(): void
    {
        config(['listmonk.preconfirm' => false]);

        User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $this->assertSame('unconfirmed', $this->remote[1]['lists'][User::NEWSLETTER]);
    }

    #[Test]
    public function a_model_without_a_valid_email_stays_out_of_listmonk(): void
    {
        User::create(['name' => 'Ali']);
        User::create(['name' => 'Ayşe', 'email' => 'not-an-email']);

        $this->assertSame([], $this->requests);
        $this->assertSame(0, Subscriber::query()->count());
    }

    #[Test]
    public function a_change_is_sent_once_and_unrelated_changes_are_not_sent(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        $this->requests = [];

        $user->update(['logins' => 5]);
        $user->save();
        $this->assertSame([], $this->requests);

        $user->update(['name' => 'Ali Veli', 'email' => 'veli@example.com']);

        $this->assertSame(['GET /api/subscribers/1', 'PUT /api/subscribers/1'], $this->requests);
        $this->assertSame('Ali Veli', $this->remote[1]['name']);
        $this->assertSame('veli@example.com', $this->remote[1]['email']);
        $this->assertSame('veli@example.com', $user->subscriber?->email);
    }

    #[Test]
    public function an_update_keeps_what_listmonk_knows(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com', 'city' => 'İzmir']);
        $this->remote[1] = [
            ...$this->remote[1],
            'status' => 'blocklisted',
            'attribs' => ['city' => 'İzmir', 'source' => 'import'],
            'lists' => [User::ANNOUNCEMENTS => 'confirmed', User::NEWSLETTER => 'unsubscribed', 9 => 'confirmed'],
        ];

        $user->update(['city' => 'Ankara']);

        $this->assertSame('blocklisted', $this->remote[1]['status']);
        $this->assertSame(['city' => 'Ankara', 'source' => 'import'], $this->sorted($this->remote[1]['attribs']));
        $this->assertSame('unsubscribed', $this->remote[1]['lists'][User::NEWSLETTER]);
        $this->assertSame('confirmed', $this->remote[1]['lists'][9]);
    }

    #[Test]
    public function a_list_the_model_leaves_is_removed(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $user->update(['newsletter' => false]);

        $this->assertSame([User::ANNOUNCEMENTS], array_keys($this->remote[1]['lists']));
        $this->assertSame([User::ANNOUNCEMENTS], $user->subscriber?->lists);
    }

    #[Test]
    public function an_email_listmonk_already_has_is_adopted(): void
    {
        $id = $this->remoteSubscriber('ali@example.com', [9 => 'confirmed'], attribs: ['source' => 'form']);

        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $this->assertSame(['POST /api/subscribers', 'GET /api/subscribers', "PUT /api/subscribers/{$id}"], $this->requests);
        $this->assertSame($id, $user->subscriber?->listmonk_id);
        $this->assertSame([9, User::ANNOUNCEMENTS, User::NEWSLETTER], array_keys($this->remote[$id]['lists']));
        $this->assertSame('form', $this->remote[$id]['attribs']['source']);
    }

    #[Test]
    public function a_model_that_loses_its_email_is_removed(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $user->update(['email' => null]);

        $this->assertSame([], $this->remote);
        $this->assertNull($user->subscriber);
    }

    #[Test]
    public function a_soft_deleted_model_is_removed_and_a_restored_one_returns(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $user->delete();

        $this->assertSame([], $this->remote);
        $this->assertSame(0, Subscriber::query()->count());

        $user->restore();

        $this->assertSame(['ali@example.com'], array_column($this->remote, 'email'));
        $this->assertSame(1, Subscriber::query()->count());
    }

    #[Test]
    public function a_deleted_model_is_removed(): void
    {
        $organizer = Organizer::create(['title' => 'Sahne', 'email' => 'sahne@example.com']);
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        $organizer->delete();
        $user->forceDelete();

        $this->assertSame([], $this->remote);
        $this->assertSame(0, Subscriber::query()->count());
    }

    #[Test]
    public function models_sharing_an_email_share_the_subscriber(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        $organizer = Organizer::create(['title' => 'Sahne', 'email' => 'ali@example.com']);

        $this->assertCount(1, $this->remote);
        $this->assertSame([User::ANNOUNCEMENTS, User::NEWSLETTER, Organizer::ORGANIZERS], array_keys($this->remote[1]['lists']));

        $organizer->delete();

        $this->assertSame([User::ANNOUNCEMENTS, User::NEWSLETTER], array_keys($this->remote[1]['lists']));

        $user->forceDelete();

        $this->assertSame([], $this->remote);
    }

    #[Test]
    public function a_subscriber_deleted_in_listmonk_is_not_created_again(): void
    {
        $user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        $this->remote = [];

        $user->update(['name' => 'Veli']);

        $this->assertSame([], $this->remote);

        $user->delete();

        $this->assertSame(0, Subscriber::query()->count());
    }

    #[Test]
    public function nothing_is_sent_before_the_transaction_commits(): void
    {
        try {
            DB::transaction(function () {
                User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

                throw new \RuntimeException();
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame([], $this->requests);
    }

    #[Test]
    public function nothing_is_sent_while_listmonk_is_not_configured(): void
    {
        config(['listmonk.token' => '']);
        User::create(['name' => 'Ali', 'email' => 'ali@example.com']);

        config(['listmonk.token' => 'secret', 'listmonk.enabled' => false]);
        User::create(['name' => 'Ayşe', 'email' => 'ayse@example.com'])->syncSubscriber();

        $this->assertSame([], $this->requests);
    }

    #[Test]
    public function a_model_can_be_synced_by_hand(): void
    {
        $user = User::withoutEvents(fn () => User::create(['name' => 'Ali', 'email' => 'ali@example.com']));

        $user->syncSubscriber();

        $this->assertSame(['ali@example.com'], array_column($this->remote, 'email'));
    }

    #[Test]
    public function the_trait_refuses_a_model_without_the_contract(): void
    {
        $this->expectException(LogicException::class);

        Guest::create(['email' => 'guest@example.com']);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
