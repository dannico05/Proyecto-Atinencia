<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\TeachingEligibility\Verification\Presentation\Livewire\EligibilityVerificationComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('eligibility-checks', EligibilityVerificationComponent::class)
    ->name('teaching-eligibility.verifications.index');
