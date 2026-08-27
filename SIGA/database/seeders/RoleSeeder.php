<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrator = Role::query()->firstOrCreate(['name' => 'Administrador']);
        $administrator->permissions()->sync(Permission::query()->pluck('id'));

        $coordinator = Role::query()->firstOrCreate(['name' => 'Coordinadora de Docencia']);
        $coordinator->permissions()->sync(Permission::query()
            ->whereIn('module', ['teachers', 'eligibility_checks'])
            ->whereNotIn('action', ['delete', 'resolve_technical_note'])
            ->pluck('id'));

        $viewer = Role::query()->firstOrCreate(['name' => 'Consulta']);
        $viewer->permissions()->sync(Permission::query()->whereIn('name', [
            'teachers.view', 'teachers.search',
            'eligibility_catalogs.view', 'eligibility_catalogs.search',
            'eligibility_checks.view',
        ])->pluck('id'));

        Role::query()->whereIn('name', ['Superadmin', 'Admin'])->get()->each(function (Role $role): void {
            $role->users()->detach();
            $role->permissions()->detach();
            $role->delete();
        });
    }
}
