<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EligibilityCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification;

final class EligibilityCheckController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user?->getAuthIdentifier(),
            'name' => $user?->name,
            'email' => $user?->email,
        ]);
    }

    public function show(EligibilityCheck $eligibilityCheck): JsonResponse
    {
        Gate::authorize('viewAny', EligibilityVerification::class);

        $eligibilityCheck->load([
            'catalog',
            'assignment.teacher',
            'assignment.group.course.career',
            'assignment.group.academicTerm',
        ]);

        return response()->json([
            'id' => $eligibilityCheck->id,
            'result' => $eligibilityCheck->result,
            'provisional' => (bool) $eligibilityCheck->provisional,
            'teacher' => [
                'national_id' => $eligibilityCheck->assignment->teacher->national_id,
                'name' => $eligibilityCheck->assignment->teacher->fullName(),
            ],
            'course' => [
                'code' => $eligibilityCheck->assignment->group->course->code,
                'name' => $eligibilityCheck->assignment->group->course->name,
                'career' => $eligibilityCheck->assignment->group->course->career->name,
            ],
            'catalog' => $eligibilityCheck->catalog ? [
                'version' => $eligibilityCheck->catalog->version,
                'agreement' => $eligibilityCheck->catalog->university_council_agreement,
                'gazette' => $eligibilityCheck->catalog->gazette_number,
            ] : null,
            'checked_at' => $eligibilityCheck->created_at?->toIso8601String(),
        ]);
    }
}
