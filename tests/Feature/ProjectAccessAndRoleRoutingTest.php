<?php

namespace Tests\Feature;

use App\Livewire\Member\MemberDashboard;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectAccessAndRoleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_open_routes_team_lead_to_lead_dashboard(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $project = Project::create($this->projectPayload('Lead Project', $lead->id));
        $team = Team::create(['name' => 'Lead Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);
        $team->members()->attach($lead->id, ['role' => 'lead']);

        $this->actingAs($lead)
            ->get(route('projects.open', ['project' => $project, 'team' => $team->id]))
            ->assertRedirect(route('lead.dashboard', ['team' => $team->id]));

        $this->assertSame($team->id, session('active_team_id'));
        $this->assertSame('lead', session('active_project_role'));
    }

    public function test_project_open_routes_member_to_member_dashboard(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        $project = Project::create($this->projectPayload('Member Project', $lead->id));
        $team = Team::create(['name' => 'Member Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->get(route('projects.open', ['project' => $project, 'team' => $team->id]))
            ->assertRedirect(route('member.dashboard', ['team' => $team->id, 'project' => $project->id]));

        $this->assertSame($team->id, session('active_team_id'));
        $this->assertSame('member', session('active_project_role'));
    }

    public function test_unassigned_project_open_redirects_to_project_picker(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        $project = Project::create($this->projectPayload('Private Project', $lead->id));
        Team::create(['name' => 'Private Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);

        $this->actingAs($member)
            ->get(route('projects.open', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('error');
    }

    public function test_member_dashboard_drops_unauthorized_team_filter(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        $project = Project::create($this->projectPayload('Visible Project', $lead->id));
        $ownedTeam = Team::create(['name' => 'Owned Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);
        $otherTeam = Team::create(['name' => 'Other Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);
        $ownedTeam->members()->attach($member->id, ['role' => 'member']);

        Task::create([
            'title' => 'Owned Task',
            'project_id' => $project->id,
            'team_id' => $ownedTeam->id,
            'assigned_to' => $member->id,
            'created_by' => $lead->id,
            'start_date' => '2026-07-01',
            'due_date' => '2026-07-03',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->actingAs($member);

        Livewire::withQueryParams(['team' => $otherTeam->id])
            ->test(MemberDashboard::class)
            ->assertSet('filterTeam', 0);
    }

    private function projectPayload(string $name, int $creatorId): array
    {
        return [
            'name' => $name,
            'description' => 'Test project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $creatorId,
        ];
    }
}
