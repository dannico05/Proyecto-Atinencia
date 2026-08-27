<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Src\TeachingEligibility\Verification\Application\UseCases\ListEligibilityChecksUseCase;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(
    static fn (): int => app(ListEligibilityChecksUseCase::class)->refreshExpiredTechnicalNotes(),
)
    ->name('teaching-eligibility:expire-technical-notes')
    ->dailyAt('00:05')
    ->withoutOverlapping();
