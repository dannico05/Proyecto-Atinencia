<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'eligibility_checks.resolve_technical_note'],
            [
                'module' => 'eligibility_checks',
                'action' => 'resolve_technical_note',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'eligibility_checks.resolve_technical_note')
            ->value('id');
        $administratorId = DB::table('roles')->where('name', 'Administrador')->value('id');

        if ($permissionId !== null && $administratorId !== null) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'eligibility_checks.resolve_technical_note')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
