<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EligibilityCatalog;
use App\Models\EligibilityCheck;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Contracts\View\View;
use Src\TeachingEligibility\Verification\Application\UseCases\ListEligibilityChecksUseCase;

final class DashboardController extends Controller
{
    public function __invoke(ListEligibilityChecksUseCase $eligibilityChecks): View
    {
        $eligibilityChecks->refreshExpiredTechnicalNotes();

        return view('dashboard', [
            'metrics' => [
                'teachers' => Teacher::query()->where('active', true)->count(),
                'teachersWithCredentials' => Teacher::query()->where('active', true)->whereHas('credentials')->count(),
                'catalogVersions' => EligibilityCatalog::query()->count(),
                'verifications' => EligibilityCheck::query()->distinct('teaching_assignment_id')->count('teaching_assignment_id'),
            ],
            'alerts' => [
                'blocked' => TeachingAssignment::query()->where('status', 'blocked')->count(),
                'manual' => TeachingAssignment::query()->where('status', 'pending_manual_approval')->count(),
                'technical' => TeachingAssignment::query()->where('status', 'technical_note_pending')->count(),
                'expired' => TeachingAssignment::query()->where('status', 'technical_note_expired')->count(),
            ],
            'recentChecks' => EligibilityCheck::query()
                ->with(['assignment.teacher', 'assignment.group.course'])
                ->whereIn('id', EligibilityCheck::query()
                    ->selectRaw('MAX(id)')
                    ->groupBy('teaching_assignment_id'))
                ->latest()
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
