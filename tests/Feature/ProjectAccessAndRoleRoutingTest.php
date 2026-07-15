<?php

namespace Tests\Feature;

use App\Livewire\Lead\LeadJournalReview;
use App\Livewire\Member\MemberDashboard;
use App\Models\JournalLog;
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

    public function test_user_can_switch_to_an_assigned_workspace_context_only(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create($this->projectPayload('Switchable Project', $lead->id));
        $team = Team::create(['name' => 'Switchable Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('workspace.context.switch'), [
                'context' => $project->id.':'.$team->id.':member',
                'return_route' => 'member.logs',
            ])
            ->assertRedirect(route('member.logs', ['team' => $team->id, 'project' => $project->id]));

        $this->assertSame($project->id, session('active_project_id'));
        $this->assertSame($team->id, session('active_team_id'));
        $this->assertSame('member', session('active_project_role'));

        $outsider = User::factory()->create(['role' => 'member']);
        $this->actingAs($outsider)
            ->post(route('workspace.context.switch'), [
                'context' => $project->id.':'.$team->id.':member',
            ])
            ->assertNotFound();
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

    public function test_team_lead_journal_review_shows_member_logs_for_selected_project(): void
    {
        $earlierDate = now()->subDay()->toDateString();
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create($this->projectPayload('Journal Project', $lead->id));
        $team = Team::create(['name' => 'Journal Team', 'project_id' => $project->id, 'lead_id' => $lead->id]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $task = Task::create([
            'title' => 'Write integration notes',
            'project_id' => $project->id,
            'team_id' => $team->id,
            'assigned_to' => $member->id,
            'created_by' => $lead->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        JournalLog::create([
            'user_id' => $member->id,
            'task_id' => $task->id,
            'team_id' => $team->id,
            'log_date' => now()->toDateString(),
            'minutes' => 45,
            'notes' => 'Connected from member journal.',
        ]);

        JournalLog::create([
            'user_id' => $member->id,
            'task_id' => $task->id,
            'team_id' => $team->id,
            'log_date' => $earlierDate,
            'minutes' => 15,
            'notes' => 'Earlier journal entry.',
        ]);

        $this->actingAs($lead)
            ->withSession([
                'active_project_id' => $project->id,
                'active_team_id' => $team->id,
                'active_project_role' => 'lead',
            ]);

        Livewire::test(LeadJournalReview::class)
            ->assertSee('Write integration notes')
            ->assertSee('Connected from member journal.')
            ->assertDontSee('Earlier journal entry.')
            ->assertSee('0h 45m')
            ->set('dateFrom', $earlierDate)
            ->assertSee('Earlier journal entry.')
            ->assertSee('1h 0m');
    }
    public function test_team_lead_can_open_member_dashboard_for_same_project_member_role(): void
    {
        $user = User::factory()->create(['role' => 'team_lead']);
        $project = Project::create($this->projectPayload('Mixed Role Project', $user->id));
        $leadTeam = Team::create(['name' => 'Lead Team', 'project_id' => $project->id, 'lead_id' => $user->id]);
        $memberTeam = Team::create(['name' => 'Member Team', 'project_id' => $project->id, 'lead_id' => $user->id]);

        $leadTeam->members()->attach($user->id, ['role' => 'lead']);
        $memberTeam->members()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)
            ->withSession([
                'active_project_id' => $project->id,
                'active_team_id' => $leadTeam->id,
                'active_project_role' => 'lead',
            ])
            ->get(route('member.dashboard', ['team' => $leadTeam->id, 'project' => $project->id]))
            ->assertOk();

        Livewire::withQueryParams(['team' => $leadTeam->id, 'project' => $project->id])
            ->test(MemberDashboard::class)
            ->assertSet('filterProject', $project->id)
            ->assertSet('filterTeam', 0);
    }

    public function test_team_assigned_projects_include_pivot_linked_projects(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $firstProject = Project::create($this->projectPayload('Pivot Project A', $lead->id));
        $secondProject = Project::create($this->projectPayload('Pivot Project B', $lead->id));
        $outsideProject = Project::create($this->projectPayload('Outside Project', $lead->id));
        $team = Team::create(['name' => 'Pivot Team', 'lead_id' => $lead->id]);

        $team->projects()->attach([$firstProject->id, $secondProject->id]);
        $team->refresh();

        $this->assertEqualsCanonicalizing(
            [$firstProject->id, $secondProject->id],
            $team->assignedProjects()->pluck('id')->all()
        );
        $this->assertTrue($team->isAssignedToProject($firstProject->id));
        $this->assertTrue($team->isAssignedToProject($secondProject->id));
        $this->assertFalse($team->isAssignedToProject($outsideProject->id));
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



