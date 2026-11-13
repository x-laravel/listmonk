<?php

namespace XLaravel\Listmonk\Tests;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use XLaravel\Listmonk\Models\Subscriber;
use XLaravel\Listmonk\Tests\Fixtures\Organizer;
use XLaravel\Listmonk\Tests\Fixtures\User;

class PruningTest extends TestCase
{
    public function test_subscribers_of_models_that_no_longer_exist_are_removed_from_listmonk(): void
    {
        $gone = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        User::create(['name' => 'Ayşe', 'email' => 'ayse@example.com']);
        DB::table('users')->where('id', $gone->id)->delete();

        $this->prune();

        $this->assertSame(['ayse@example.com'], array_column($this->remote, 'email'));
        $this->assertSame(['ayse@example.com'], Subscriber::query()->pluck('email')->all());
    }

    public function test_a_shared_subscriber_only_loses_the_lists_of_the_missing_model(): void
    {
        User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        Organizer::create(['title' => 'Sahne', 'email' => 'ali@example.com']);
        DB::table('organizers')->delete();

        $this->prune();

        $this->assertSame([User::ANNOUNCEMENTS, User::NEWSLETTER], array_keys($this->remote[1]['lists']));
        $this->assertSame(1, Subscriber::query()->count());
    }

    public function test_unknown_types_are_left_alone(): void
    {
        User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
        Subscriber::query()->update(['subscribable_type' => 'missing']);

        $this->prune();

        $this->assertSame(1, Subscriber::query()->count());
        $this->assertCount(1, $this->remote);
    }

    public function test_pruning_is_scheduled_daily_in_chunks_of_a_thousand(): void
    {
        $event = $this->scheduledPruning();

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertStringContainsString('--chunk=1000', (string) $event->command);
    }

    public function test_the_chunk_size_can_be_configured(): void
    {
        config(['listmonk.prune.chunk' => 250]);

        $this->assertStringContainsString('--chunk=250', (string) $this->scheduledPruning()?->command);
    }

    public function test_the_schedule_accepts_a_cron_expression(): void
    {
        config(['listmonk.prune.schedule' => '30 3 * * *']);

        $this->assertSame('30 3 * * *', $this->scheduledPruning()?->expression);
    }

    public function test_the_schedule_accepts_a_frequency_with_arguments(): void
    {
        config(['listmonk.prune.schedule' => 'weeklyOn:1,04:00']);

        $this->assertSame('0 4 * * 1', $this->scheduledPruning()?->expression);
    }

    public function test_an_unknown_frequency_is_refused(): void
    {
        config(['listmonk.prune.schedule' => 'sometimes']);

        $this->expectException(InvalidArgumentException::class);

        $this->scheduledPruning();
    }

    public function test_the_schedule_can_be_turned_off(): void
    {
        config(['listmonk.prune.enabled' => false]);

        $this->assertNull($this->scheduledPruning());
    }

    public function test_nothing_is_scheduled_while_listmonk_is_not_configured(): void
    {
        config(['listmonk.url' => null]);

        $this->assertNull($this->scheduledPruning());
    }

    protected function prune(): void
    {
        $this->assertSame(0, Artisan::call('model:prune', ['--model' => [Subscriber::class]]));
    }

    protected function scheduledPruning(): ?ScheduledEvent
    {
        $this->app?->forgetInstance(Schedule::class);

        $events = $this->app?->make(Schedule::class)->events() ?? [];

        foreach ($events as $event) {
            if (str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'Subscriber')) {
                return $event;
            }
        }

        return null;
    }
}
