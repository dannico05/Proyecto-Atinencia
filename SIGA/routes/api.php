<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\EligibilityCheckController;
use Illuminate\Support\Facades\Route;

Route::post('auth/token', [ApiTokenController::class, 'store'])
    ->middleware('throttle:10,1');

Route::middleware('api.jwt')->group(function (): void {
    Route::get('me', [EligibilityCheckController::class, 'me']);
    Route::get('eligibility-checks/{eligibilityCheck}', [EligibilityCheckController::class, 'show']);
});
