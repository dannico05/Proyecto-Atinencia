<?php

declare(strict_types=1);

namespace Src\Shared\OfficialTime\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Src\Shared\OfficialTime\Domain\Contracts\OfficialTimeProviderInterface;
use Throwable;

final class CostaRicaOfficialTimeProvider implements OfficialTimeProviderInterface
{
    public function now(): DateTimeImmutable
    {
        try {
            $isoDate = Cache::remember('official-time.costa-rica', now()->addMinutes(10), function (): string {
                $response = Http::acceptJson()->timeout(3)->get((string) config('services.official_time.url'));
                $response->throw();
                $payload = $response->json();

                return (string) ($payload['datetime'] ?? $payload['dateTime'] ?? '');
            });

            if ($isoDate !== '') {
                return new DateTimeImmutable($isoDate);
            }
        } catch (Throwable) {
            // The external clock is an enhancement; business continuity uses the configured local timezone.
        }

        return new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica'));
    }
}
