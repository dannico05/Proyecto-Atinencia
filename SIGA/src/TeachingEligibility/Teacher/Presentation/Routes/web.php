<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\TeachingEligibility\Teacher\Presentation\Livewire\TeacherComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('teachers', TeacherComponent::class)
    ->name('teaching-eligibility.teachers.index');
