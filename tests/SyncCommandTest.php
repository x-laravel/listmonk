<?php

namespace XLaravel\Listmonk\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\Listmonk\Tests\Fixtures\Guest;
use XLaravel\Listmonk\Tests\Fixtures\Organizer;
use XLaravel\Listmonk\Tests\Fixtures\User;

class SyncCommandTest extends TestCase
{
    #[Test]
    public function every_record_of_the_given_models_is_synced(): void
    {
        User::withoutEvents(function () {
            User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
            User::create(['name' => 'Ayşe', 'email' => 'ayse@example.com']);
            User::create(['name' => 'Silinen', 'email' => 'silinen@example.com'])->delete();
        });
        Organizer::withoutEvents(fn () => Organizer::create(['title' => 'Sahne', 'email' => 'sahne@example.com']));

        $status = Artisan::call('listmonk:sync', ['model' => [User::class, Organizer::class], '--chunk' => 1]);

        $this->assertSame(0, $status);
        $this->assertSame(['ali@example.com', 'ayse@example.com', 'sahne@example.com'], array_column($this->remote, 'email'));
    }

    #[Test]
    public function a_model_that_is_not_subscribable_is_refused(): void
    {
        $this->assertSame(1, Artisan::call('listmonk:sync', ['model' => [Guest::class]]));
        $this->assertSame([], $this->requests);
    }

    #[Test]
    public function the_command_fails_while_listmonk_is_not_configured(): void
    {
        config(['listmonk.token' => null]);

        $this->assertSame(1, Artisan::call('listmonk:sync', ['model' => [User::class]]));
    }
}
