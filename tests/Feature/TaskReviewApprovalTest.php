<?php

namespace Tests\Feature;

use App\Livewire\Lead\LeadTaskManager;
use App\Livewire\Member\MemberDashboard;
use App\Models\InAppNotification;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskReviewApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_mark_a_task_done_while_it_is_under_review(): void
    {
        [$lead, $member, $team, $task] = $this->reviewContext();

        $this->actingAs($member)->withSession([
            'active_project_id' => $task->project_id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberDashboard::class)
            ->call('setStatus', $task->id, 'done')
            ->assertSet('flash', "This task is awaiting your team lead's approval and cannot be marked done yet.");

        $this->assertSame('review', $task->fresh()->status);
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'review',
        ]);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_member_can_still_revise_a_task_back_to_in_progress_while_under_review(): void
    {
        [$lead, $member, $team, $task] = $this->reviewContext();

        $this->actingAs($member)->withSession([
            'active_project_id' => $task->project_id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberDashboard::class)->call('setStatus', $task->id, 'in_progress');

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_team_lead_can_approve_a_review_and_mark_the_task_done(): void
    {
        [$lead, $member, $team, $task] = $this->reviewContext();

        $this->actingAs($lead)->withSession([
            'active_project_id' => $task->project_id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ]);

        Livewire::test(LeadTaskManager::class)
            ->call('approveMemberReview', $task->id, $member->id);

        $this->assertSame('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'done',
            'progress' => 100,
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $member->id,
            'type' => 'task_review_approved',
        ]);
    }

    public function test_team_lead_cannot_approve_a_review_for_a_task_outside_their_teams(): void
    {
        [, $member, , $task] = $this->reviewContext();
        $otherLead = User::factory()->create(['role' => 'team_lead']);

        $this->actingAs($otherLead);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(LeadTaskManager::class)
            ->call('approveMemberReview', $task->id, $member->id);
    }

    public function test_approving_a_task_not_under_review_is_a_no_op(): void
    {
        [$lead, $member, $team, $task] = $this->reviewContext();

        // Member revises back to in_progress before the lead acts.
        TaskMemberProgress::where('task_id', $task->id)
            ->where('user_id', $member->id)
            ->update(['status' => 'in_progress']);
        $task->update(['status' => 'in_progress']);

        $this->actingAs($lead)->withSession([
            'active_project_id' => $task->project_id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ]);

        Livewire::test(LeadTaskManager::class)
            ->call('approveMemberReview', $task->id, $member->id);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $member->id,
            'type' => 'task_review_approved',
        ]);
    }

    /** @return array{0: User, 1: User, 2: Team, 3: Task} */
    private function reviewContext(): array
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Review Gate Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Review Gate Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $task = Task::create([
            'title' => 'Ship the review gate',
            'project_id' => $project->id,
            'team_id' => $team->id,
            'assigned_to' => $member->id,
            'created_by' => $lead->id,
            'start_date' => '2026-07-01',
            'due_date' => '2026-07-31',
            'status' => 'review',
            'priority' => 'medium',
        ]);
        TaskMemberProgress::create([
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'review',
            'progress' => 80,
            'started_at' => now(),
        ]);

        return [$lead, $member, $team, $task];
    }
}
