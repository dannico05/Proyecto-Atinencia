<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingGroup;
use App\Models\User;
use Database\Seeders\TeachingEligibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertSeeText('Panel principal')
            ->assertSeeText('Casos que requieren atención')
            ->assertDontSeeText('Acceso rápido')
            ->assertDontSeeText('Aulas')
            ->assertDontSeeText('Riesgos');
    }

    public function test_dashboard_counts_and_lists_only_the_latest_check_per_assignment(): void
    {
        $this->seed(TeachingEligibilitySeeder::class);
        Http::fake(['*' => Http::response(['datetime' => '2026-08-27T03:00:00-06:00'])]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $assignment = TeachingAssignment::query()->create([
            'teaching_group_id' => TeachingGroup::query()->firstOrFail()->id,
            'teacher_id' => Teacher::query()->firstOrFail()->id,
            'status' => 'confirmed',
        ]);
        $assignment->checks()->create([
            'result' => 'not_eligible',
            'provisional' => false,
            'reason' => 'Initial result.',
        ]);
        $latest = $assignment->checks()->create([
            'result' => 'technical_note',
            'provisional' => false,
            'reason' => 'Current result.',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', static fn (array $metrics): bool => $metrics['verifications'] === 1)
            ->assertViewHas('recentChecks', static fn ($checks): bool => $checks->count() === 1
                && $checks->first()->is($latest));
    }
}
