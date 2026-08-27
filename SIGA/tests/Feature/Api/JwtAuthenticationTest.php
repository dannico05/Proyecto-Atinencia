<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\EligibilityCheck;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Security\JwtService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeachingEligibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\TeachingEligibility\Verification\Application\DTOs\EligibilityCheckDTO;
use Src\TeachingEligibility\Verification\Application\UseCases\VerifyAssignmentUseCase;
use Tests\TestCase;

final class JwtAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('jwt.secret', str_repeat('test-secret-', 4));
    }

    #[Test]
    public function valid_credentials_issue_a_jwt_that_authenticates_api_requests(): void
    {
        $user = User::factory()->create([
            'email' => 'api@siga.test',
            'password' => 'Password123!',
        ]);

        $tokenResponse = $this->postJson('/api/auth/token', [
            'email' => 'api@siga.test',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonStructure(['token_type', 'access_token', 'expires_in']);

        $this->withToken($tokenResponse->json('access_token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => 'api@siga.test']);
    }

    #[Test]
    public function protected_api_routes_reject_requests_without_a_jwt(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    #[Test]
    public function eligibility_check_api_requires_and_honors_view_permission(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            TeachingEligibilitySeeder::class,
        ]);

        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();
        app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $check = EligibilityCheck::query()->firstOrFail();

        $unauthorizedUser = User::factory()->create();
        $this->withToken(app(JwtService::class)->issue($unauthorizedUser))
            ->getJson('/api/eligibility-checks/'.$check->id)
            ->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::query()->where('name', 'Consulta')->firstOrFail());

        $this->withToken(app(JwtService::class)->issue($viewer))
            ->getJson('/api/eligibility-checks/'.$check->id)
            ->assertOk()
            ->assertJsonPath('result', 'eligible');
    }
}
