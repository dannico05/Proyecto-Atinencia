<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Presentation\Policies;

use App\Models\User;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;

final class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('teachers.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('teachers.search');
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Teacher|string|null $teacher = null): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Teacher|string|null $teacher = null): bool
    {
        return $this->canManage($user) && $user->hasPermissionTo('teachers.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('teachers.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('teachers.export_excel');
    }

    private function canManage(User $user): bool
    {
        return $user->hasAnyRole(['Administrador', 'Coordinadora de Docencia'])
            && $user->hasAnyPermission(['teachers.create', 'teachers.edit']);
    }
}
