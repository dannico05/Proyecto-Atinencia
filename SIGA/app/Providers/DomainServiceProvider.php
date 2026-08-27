<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Infrastructure\Persistence\Repositories\EloquentPermissionRepository;
use Src\IdentityAccess\Permission\Presentation\Policies\PermissionPolicy;
use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Infrastructure\Persistence\Repositories\EloquentRoleRepository;
use Src\IdentityAccess\Role\Presentation\Policies\RolePolicy;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Src\Shared\Export\Infrastructure\SpatieExcelExporter;
use Src\Shared\Export\Infrastructure\SpatiePdfExporter;
use Src\Shared\OfficialTime\Domain\Contracts\OfficialTimeProviderInterface;
use Src\Shared\OfficialTime\Infrastructure\CostaRicaOfficialTimeProvider;
use Src\TeachingEligibility\Catalog\Domain\Contracts\EligibilityCatalogRepositoryInterface;
use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;
use Src\TeachingEligibility\Catalog\Infrastructure\Persistence\Repositories\EloquentEligibilityCatalogRepository;
use Src\TeachingEligibility\Catalog\Presentation\Policies\EligibilityCatalogPolicy;
use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;
use Src\TeachingEligibility\Teacher\Infrastructure\Persistence\Repositories\EloquentTeacherRepository;
use Src\TeachingEligibility\Teacher\Presentation\Policies\TeacherPolicy;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;
use Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification;
use Src\TeachingEligibility\Verification\Infrastructure\Persistence\Repositories\EloquentEligibilityCheckRepository;
use Src\TeachingEligibility\Verification\Presentation\Policies\EligibilityVerificationPolicy;

final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $domainBindings = [
        RoleRepositoryInterface::class => EloquentRoleRepository::class,
        PermissionRepositoryInterface::class => EloquentPermissionRepository::class,
        ExcelExporterInterface::class => SpatieExcelExporter::class,
        PdfExporterInterface::class => SpatiePdfExporter::class,
        TeacherRepositoryInterface::class => EloquentTeacherRepository::class,
        EligibilityCatalogRepositoryInterface::class => EloquentEligibilityCatalogRepository::class,
        EligibilityCheckRepositoryInterface::class => EloquentEligibilityCheckRepository::class,
        OfficialTimeProviderInterface::class => CostaRicaOfficialTimeProvider::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private array $domainPolicies = [
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        Teacher::class => TeacherPolicy::class,
        EligibilityCatalog::class => EligibilityCatalogPolicy::class,
        EligibilityVerification::class => EligibilityVerificationPolicy::class,
    ];

    public function register(): void
    {
        foreach ($this->domainBindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerAdministratorBypass();
        $this->loadContextRoutes();
    }

    private function registerPolicies(): void
    {
        foreach ($this->domainPolicies as $entity => $policy) {
            Gate::policy($entity, $policy);
        }
    }

    /**
     * The system administrator passes every authorization check. The
     * RoleSeeder also syncs every existing permission — this is the
     * safety net that also covers permissions introduced after the last
     * seed run, without needing to re-sync anything.
     */
    private function registerAdministratorBypass(): void
    {
        Gate::before(function (Authenticatable $user): ?bool {
            return method_exists($user, 'hasRole') && $user->hasRole('Administrador')
                ? true
                : null;
        });
    }

    private function loadContextRoutes(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        foreach (File::glob(base_path('src/*/*/Presentation/Routes/web.php')) as $routeFile) {
            $this->loadRoutesFrom($routeFile);
        }
    }
}
