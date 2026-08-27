<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Presentation\Policies;

use App\Models\User;

final class EligibilityVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_checks.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_checks.create');
    }

    public function createTechnicalNote(User $user): bool
    {
        return $user->hasAnyRole(['Administrador', 'Coordinadora de Docencia'])
            && $user->hasPermissionTo('eligibility_checks.create_technical_note');
    }

    public function approveManual(User $user): bool
    {
        return $user->hasAnyRole(['Administrador', 'Coordinadora de Docencia'])
            && $user->hasPermissionTo('eligibility_checks.approve_manual');
    }

    public function resolveTechnicalNote(User $user): bool
    {
        return $user->hasRole('Administrador')
            && $user->hasPermissionTo('eligibility_checks.resolve_technical_note');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_checks.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_checks.export_excel');
    }
}
