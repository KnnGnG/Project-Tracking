<?php

namespace Tests\Feature;

use App\Livewire\Admin\TeamManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Member\MemberLeadEvaluation;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamLeadEvaluation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataIntegrityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_user_who_owns_project_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'team_lead']);
        $project = Project::create([
            'name' => 'Protected Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserManager::class)
            ->call('confirmDelete', $owner->id)
            ->call('deleteConfirmed')
            ->assertSet('confirmingDelete', true)
            ->assertSet('deleteError', fn ($message) => str_contains($message, 'Reassign'));

        $this->assertNotNull($owner->fresh());
        $this->assertNotNull($project->fresh());
    }

    public function test_team_is_reused_only_when_name_lead_and_members_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        $firstMember = User::factory()->create(['role' => 'member']);
        $secondMember = User::factory()->create(['role' => 'member']);
        $team = Team::create(['name' => 'Delivery', 'lead_id' => $lead->id]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($firstMember->id, ['role' => 'member']);

        $this->actingAs($admin);

        Livewire::test(TeamManager::class)
            ->call('openCreate')
            ->set('name', 'Delivery')
            ->set('leadId', $lead->id)
            ->set('memberIds', [$firstMember->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('teams', 1);

        Livewire::test(TeamManager::class)
            ->call('openCreate')
            ->set('name', 'Delivery')
            ->set('leadId', $lead->id)
            ->set('memberIds', [$secondMember->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('teams', 2);
        $this->assertTrue($team->fresh()->regularMembers->contains($firstMember));
        $this->assertFalse($team->fresh()->regularMembers->contains($secondMember));
    }

    public function test_second_lead_evaluation_for_same_period_updates_existing_record(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Evaluation Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Evaluation Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        $component = Livewire::test(MemberLeadEvaluation::class)
            ->set('selectedTeamId', $team->id)
            ->set('periodStart', '2026-07-01')
            ->set('periodEnd', '2026-07-31')
            ->set('leadershipScore', 2)
            ->call('save')
            ->assertHasNoErrors();

        $component
            ->set('leadershipScore', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('team_lead_evaluations', 1);
        $this->assertSame(5, TeamLeadEvaluation::first()->leadership_score);
    }

    public function test_null_periods_cannot_bypass_the_active_lead_evaluation_constraint(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $team = Team::create(['name' => 'Constraint Team', 'lead_id' => $lead->id]);

        TeamLeadEvaluation::create([
            'team_id' => $team->id,
            'evaluator_id' => $member->id,
            'lead_id' => $lead->id,
            'period_start' => null,
            'period_end' => null,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        TeamLeadEvaluation::create([
            'team_id' => $team->id,
            'evaluator_id' => $member->id,
            'lead_id' => $lead->id,
            'period_start' => null,
            'period_end' => null,
        ]);
    }
}
