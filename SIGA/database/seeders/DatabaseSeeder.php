<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            TeachingEligibilitySeeder::class,
        ]);

        foreach ([
            ['role' => 'Administrador', 'name' => 'Administración SIGA', 'email' => 'admin@gmail.com'],
            ['role' => 'Coordinadora de Docencia', 'name' => 'Coordinación de Docencia', 'email' => 'coordinadora@gmail.com'],
            ['role' => 'Consulta', 'name' => 'Consulta SIGA', 'email' => 'consulta@gmail.com'],
        ] as $account) {
            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => bcrypt('12345678'),
                    'email_verified_at' => now(),
                ],
            );

            $user->roles()->sync(
                Role::query()->where('name', $account['role'])->pluck('id'),
            );
        }
    }
}
