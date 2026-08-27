<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Only capabilities that have a real screen or server action are seeded.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISSION_MAP = [
        'roles' => ['create', 'view', 'edit', 'delete', 'search', 'export_pdf', 'export_excel'],
        'permissions' => ['create', 'view', 'edit', 'delete', 'search', 'export_pdf', 'export_excel'],
        'teachers' => ['create', 'view', 'edit', 'delete', 'search', 'export_pdf', 'export_excel'],
        'eligibility_catalogs' => ['create', 'view', 'search', 'export_pdf', 'export_excel'],
        'eligibility_checks' => ['create', 'view', 'export_pdf', 'export_excel', 'create_technical_note', 'approve_manual', 'resolve_technical_note'],
    ];

    public function run(): void
    {
        $allowedNames = [];

        foreach (self::PERMISSION_MAP as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $allowedNames[] = $name;
                Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['module' => $module, 'action' => $action],
                );
            }
        }

        Permission::query()
            ->whereIn('module', array_keys(self::PERMISSION_MAP))
            ->whereNotIn('name', $allowedNames)
            ->delete();
    }
}
