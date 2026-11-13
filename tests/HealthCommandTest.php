<?php

namespace XLaravel\Listmonk\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class HealthCommandTest extends TestCase
{
    #[Test]
    public function it_passes_when_listmonk_accepts_the_api_user(): void
    {
        $this->assertSame(0, Artisan::call('listmonk:health'));
        $this->assertSame(['GET /api/subscribers'], $this->requests);
    }

    #[Test]
    public function it_fails_with_the_answer_of_listmonk(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['message' => 'invalid API credentials'], 403)]);

        $this->assertSame(1, Artisan::call('listmonk:health'));
        $this->assertStringContainsString('403: invalid API credentials', Artisan::output());
    }

    #[Test]
    public function it_fails_while_listmonk_is_not_configured(): void
    {
        config(['listmonk.url' => '']);

        $this->assertSame(1, Artisan::call('listmonk:health'));
        $this->assertSame([], $this->requests);
    }
}
