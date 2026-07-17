<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProjectManager;
use App\Livewire\Lead\TeamLeadDashboard;
use App\Models\InAppNotification;
use App\Models\Project;
use App\Models\ProjectStatusChangeRequest;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
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
        $this->assertSame('active', $request->requested_from_status);
        $this->assertSame($project->id, $request->pending_project_id);
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
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'request_id' => $request->id,
            'from_status' => 'active',
            'to_status' => 'on_hold',
            'source' => 'request',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $lead->id,
            'type' => 'project_status_request_reviewed',
        ]);
    }

    public function test_admin_can_change_lifecycle_status_directly_even_if_not_the_creator(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        [$project, $team] = $this->projectContext($creator, $admin);

        $this->actingAs($admin)->withSession($this->leadSession($project, $team));

        Livewire::test(TeamLeadDashboard::class)
            ->call('openProjectStatusForm')
            ->set('requestedProjectStatus', 'completed')
            ->set('projectStatusReason', 'All project work is complete.')
            ->call('submitProjectStatusChange')
            ->assertHasNoErrors();

        $this->assertSame('completed', $project->fresh()->status);
        $this->assertDatabaseCount('project_status_change_requests', 0);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'changed_by' => $admin->id,
            'from_status' => 'active',
            'to_status' => 'completed',
            'source' => 'creator',
            'reason' => 'All project work is complete.',
        ]);
    }

    public function test_non_admin_project_creator_must_use_approval_workflow(): void
    {
        // A lead who created their own project is not exempt from admin
        // approval: only currently-admin users get the instant-apply bypass,
        // so this still goes through the same pending-request flow as any
        // other lead (a plain `created_by` match is not enough, since roles
        // can change after a project is created).
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($lead, $lead);

        $this->actingAs($lead)->withSession($this->leadSession($project, $team));

        Livewire::test(TeamLeadDashboard::class)
            ->call('openProjectStatusForm')
            ->set('requestedProjectStatus', 'completed')
            ->set('projectStatusReason', 'All project work is complete.')
            ->call('submitProjectStatusChange')
            ->assertHasNoErrors();

        $this->assertSame('active', $project->fresh()->status);
        $request = ProjectStatusChangeRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame($lead->id, $request->requested_by);
    }

    public function test_stale_request_cannot_overwrite_a_newer_project_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($admin, $lead);
        $request = $this->submitRequest($lead, $project, $team, 'completed', 'Ready to close the project.');

        $project->update(['status' => 'on_hold']);

        $this->actingAs($admin);
        Livewire::test(ProjectManager::class)
            ->call('approveStatusRequest', $request->id);

        $this->assertSame('on_hold', $project->fresh()->status);
        $this->assertSame('superseded', $request->fresh()->status);
        $this->assertNull($request->fresh()->pending_project_id);
        $this->assertDatabaseMissing('project_status_histories', ['request_id' => $request->id]);
    }

    public function test_only_one_team_lead_can_hold_a_pending_request_for_a_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstLead = User::factory()->create(['role' => 'team_lead']);
        $secondLead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($admin, $firstLead);
        $team->members()->attach($secondLead->id, ['role' => 'lead']);
        $this->submitRequest($firstLead, $project, $team, 'on_hold', 'Waiting for an external dependency.');

        $this->actingAs($secondLead)->withSession($this->leadSession($project, $team));
        Livewire::test(TeamLeadDashboard::class)
            ->call('openProjectStatusForm')
            ->set('requestedProjectStatus', 'completed')
            ->set('projectStatusReason', 'The assigned work appears complete.')
            ->call('submitProjectStatusChange')
            ->assertHasErrors(['requestedProjectStatus']);

        $this->assertDatabaseCount('project_status_change_requests', 1);
    }

    public function test_updating_own_pending_request_reuses_admin_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($admin, $lead);
        $request = $this->submitRequest($lead, $project, $team, 'on_hold', 'Waiting for client feedback.');

        $this->submitRequest($lead, $project, $team, 'completed', 'The client confirmed final delivery.');

        $this->assertDatabaseCount('project_status_change_requests', 1);
        $this->assertSame('completed', $request->fresh()->requested_status);
        $this->assertSame(1, InAppNotification::where('user_id', $admin->id)
            ->where('type', 'project_status_requested')
            ->count());
    }

    public function test_admin_direct_change_closes_pending_request_and_records_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($admin, $lead);
        $request = $this->submitRequest($lead, $project, $team, 'on_hold', 'Waiting for client feedback.');

        $this->actingAs($admin);
        Livewire::test(ProjectManager::class)
            ->call('openEdit', $project->id)
            ->set('status', 'completed')
            ->set('statusChangeReason', 'Administrator confirmed final delivery.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('completed', $project->fresh()->status);
        $this->assertSame('superseded', $request->fresh()->status);
        $this->assertNotNull(InAppNotification::where('type', 'project_status_requested')->firstOrFail()->read_at);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'from_status' => 'active',
            'to_status' => 'completed',
            'source' => 'admin',
        ]);
    }

    public function test_stale_admin_edit_cannot_overwrite_newer_project_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project] = $this->projectContext($admin, $lead);

        $this->actingAs($admin);
        $component = Livewire::test(ProjectManager::class)
            ->call('openEdit', $project->id);

        $project->update(['status' => 'on_hold']);

        $component
            ->set('status', 'completed')
            ->set('statusChangeReason', 'Administrator confirmed final delivery.')
            ->call('save')
            ->assertHasErrors(['status']);

        $this->assertSame('on_hold', $project->fresh()->status);
        $this->assertDatabaseMissing('project_status_histories', [
            'project_id' => $project->id,
            'to_status' => 'completed',
        ]);
    }

    public function test_declining_request_requires_and_records_review_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['role' => 'team_lead']);
        [$project, $team] = $this->projectContext($admin, $lead);
        $request = $this->submitRequest($lead, $project, $team, 'on_hold', 'Waiting for client feedback.');

        $this->actingAs($admin);
        $component = Livewire::test(ProjectManager::class)
            ->call('rejectStatusRequest', $request->id)
            ->assertHasErrors(["requestReviewReasons.{$request->id}"]);

        $this->assertSame('pending', $request->fresh()->status);

        $component
            ->set("requestReviewReasons.{$request->id}", 'The project should remain active for now.')
            ->call('rejectStatusRequest', $request->id)
            ->assertHasNoErrors();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('The project should remain active for now.', $request->fresh()->review_reason);
    }

    public function test_effective_status_is_derived_consistently_from_project_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::create([
            'name' => 'Deadline Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-13',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->travelTo(Carbon::parse('2026-07-14 10:00:00'));
        $this->assertSame('overdue', $project->effectiveStatus());
        $this->assertSame('Overdue', $project->effectiveStatusLabel());

        $project->update(['status' => 'completed']);
        $this->assertSame('completed', $project->fresh()->effectiveStatus());
        $this->travelBack();
    }

    public function test_members_and_clients_cannot_open_project_status_management(): void
    {
        foreach (['member', 'client'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.projects'))
                ->assertForbidden();
        }
    }

    private function submitRequest(
        User $lead,
        Project $project,
        Team $team,
        string $status,
        string $reason,
    ): ProjectStatusChangeRequest {
        $this->actingAs($lead)->withSession($this->leadSession($project, $team));

        Livewire::test(TeamLeadDashboard::class)
            ->call('openProjectStatusForm')
            ->set('requestedProjectStatus', $status)
            ->set('projectStatusReason', $reason)
            ->call('submitProjectStatusChange')
            ->assertHasNoErrors();

        return ProjectStatusChangeRequest::where('project_id', $project->id)->firstOrFail();
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
