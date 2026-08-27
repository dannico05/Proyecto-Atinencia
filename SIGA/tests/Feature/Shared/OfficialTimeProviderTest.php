<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Src\Shared\OfficialTime\Infrastructure\CostaRicaOfficialTimeProvider;
use Tests\TestCase;

final class OfficialTimeProviderTest extends TestCase
{
    #[Test]
    public function it_consumes_the_configured_external_rest_api(): void
    {
        Cache::forget('official-time.costa-rica');
        config()->set('services.official_time.url', 'https://clock.example.test/costa-rica');
        Http::fake([
            'clock.example.test/*' => Http::response(['datetime' => '2026-08-22T10:30:00-06:00']),
        ]);

        $now = (new CostaRicaOfficialTimeProvider)->now();

        self::assertSame('2026-08-22 10:30:00', $now->format('Y-m-d H:i:s'));
        Http::assertSentCount(1);
    }
}
