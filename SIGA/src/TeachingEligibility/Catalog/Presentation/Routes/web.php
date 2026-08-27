<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\TeachingEligibility\Catalog\Presentation\Livewire\EligibilityCatalogComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('eligibility-catalogs', EligibilityCatalogComponent::class)
    ->name('teaching-eligibility.catalogs.index');
