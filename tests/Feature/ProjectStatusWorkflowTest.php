<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProjectManager;
use App\Livewire\Lead\TeamLeadDashboard;
use App\Models\InAppNotification;
use App\Models\Project;
use App\Models\ProjectStatusChangeRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_lead_requests_status_and_admin_approval_updates_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($admin, $lead);

        $this->actingAs($lead)->withSession($this->leadSession($project, $team));

        Livewire::test(TeamLeadDashboard::class)
            ->call('openProjectStatusForm')
            ->set('requestedProjectStatus', 'on_hold')
            ->set('projectStatusReason', 'Waiting for client approval.')
            ->call('submitProjectStatusChange')
            ->assertHasNoErrors();

        $request = ProjectStatusChangeRequest::firstOrFail();
        $this->assertSame('active', $project->fresh()->status);
        $this->assertSame('pending', $request->status);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $admin->id,
            'type' => 'project_status_requested',
        ]);

        $this->actingAs($admin);
        Livewire::test(ProjectManager::class)
            ->call('approveStatusRequest', $request->id);

        $this->assertSame('on_hold', $project->fresh()->status);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->reviewed_at);
        $this->assertTrue(InAppNotification::where('user_id', $lead->id)
            ->where('type', 'project_status_request_reviewed')
            ->exists());
    }

    public function test_project_creator_can_change_lifecycle_status_directly(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($lead, $lead);

        $this->actingAs($lead)->withSession($this->leadSession($project, $team));

        Livewire::test(TeamLeadDashboard::class)
            ->call('openProjectStatusForm')
            ->set('requestedProjectStatus', 'completed')
            ->set('projectStatusReason', 'All project work is complete.')
            ->call('submitProjectStatusChange')
            ->assertHasNoErrors();

        $this->assertSame('completed', $project->fresh()->status);
        $this->assertDatabaseCount('project_status_change_requests', 0);
    }

    private function projectContext(User $creator, User $lead): array
    {
        $project = Project::create([
            'name' => 'Status Workflow Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $creator->id,
        ]);
        $team = Team::create([
            'name' => 'Status Workflow Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['role' => 'lead']);

        return [$project, $team];
    }

    private function leadSession(Project $project, Team $team): array
    {
        return [
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ];
    }
}
