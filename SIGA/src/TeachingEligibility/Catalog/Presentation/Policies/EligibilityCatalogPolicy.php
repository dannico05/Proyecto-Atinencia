<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Presentation\Policies;

use App\Models\User;

final class EligibilityCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_catalogs.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Administrador')
            && $user->hasPermissionTo('eligibility_catalogs.create');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_catalogs.search');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_catalogs.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('eligibility_catalogs.export_excel');
    }
}
