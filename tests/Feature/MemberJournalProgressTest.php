<?php

namespace Tests\Feature;

use App\Livewire\Lead\LeadTaskManager;
use App\Livewire\Lead\TeamLeadDashboard;
use App\Livewire\Member\MemberDashboard;
use App\Livewire\Member\MemberJournal;
use App\Models\JournalLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberJournalProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_is_saved_per_task_and_carried_across_journal_days(): void
    {
        $this->travelTo('2026-07-16 10:00:00');

        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Progress Project',
            'description' => 'Test project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Progress Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);

        $firstTask = $this->createTask('First task', $project, $team, $lead, $member);
        $secondTask = $this->createTask('Second task', $project, $team, $lead, $member);
        TaskMemberProgress::create([
            'task_id' => $secondTask->id,
            'user_id' => $member->id,
            'status' => 'in_progress',
            'progress' => 64,
        ]);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        $component = Livewire::test(MemberJournal::class)
            ->set('selectedTaskId', (string) $firstTask->id)
            ->assertSet('progress', 1)
            ->set('logDate', '2026-07-14')
            ->set('hours', 0)
            ->set('minutes', 30)
            ->set('progress', 35)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('selectedTaskId', (string) $firstTask->id)
            ->assertSet('progress', 35)
            ->set('logDate', '2026-07-15')
            ->assertSet('progress', 35)
            ->set('selectedTaskId', (string) $secondTask->id)
            ->assertSet('progress', 64);

        $this->assertDatabaseHas('journal_logs', [
            'user_id' => $member->id,
            'task_id' => $firstTask->id,
            'progress' => 35,
        ]);
        $this->assertDatabaseHas('task_member_progress', [
            'user_id' => $member->id,
            'task_id' => $firstTask->id,
            'status' => 'in_progress',
            'progress' => 35,
        ]);

        $component
            ->set('selectedTaskId', (string) $firstTask->id)
            ->set('minutes', 10)
            ->set('progress', 100)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('selectedTaskId', '')
            ->assertSet('progress', 1);

        $this->assertSame('done', $firstTask->memberProgress()->where('user_id', $member->id)->value('status'));
        $this->assertSame(100, $firstTask->memberProgress()->where('user_id', $member->id)->value('progress'));
        $this->assertSame(100, JournalLog::where('task_id', $firstTask->id)->latest('id')->value('progress'));
    }

    public function test_saved_member_progress_is_shown_on_the_project_timeline(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member', 'name' => 'Timeline Member']);
        $project = Project::create([
            'name' => 'Timeline Progress Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Timeline Progress Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($member->id, ['role' => 'member']);
        $task = $this->createTask('Timeline task', $project, $team, $lead, $member);

        TaskMemberProgress::create([
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'in_progress',
            'progress' => 64,
            'started_at' => '2026-07-10 08:00:00',
        ]);
        JournalLog::create([
            'user_id' => $member->id,
            'task_id' => $task->id,
            'team_id' => $team->id,
            'log_date' => '2026-07-10',
            'minutes' => 30,
            'progress' => 64,
        ]);
        JournalLog::create([
            'user_id' => $member->id,
            'task_id' => $task->id,
            'team_id' => $team->id,
            'log_date' => '2026-07-11',
            'minutes' => 20,
            'progress' => 20,
        ]);

        $this->actingAs($lead)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ]);

        Livewire::test(TeamLeadDashboard::class)
            ->assertSee('Timeline Member - 64%')
            ->assertSee('Progress for this day: 64%')
            ->assertSee('Progress for this day: 20%')
            ->assertSee('Total progress: 64%')
            ->assertSee('Total progress: 20%')
            ->assertSee('Task progress');
    }

    public function test_deleting_latest_log_restores_progress_from_previous_log(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Journal Correction Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Journal Correction Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);
        $task = $this->createTask('Correctable task', $project, $team, $lead, $member);

        JournalLog::create([
            'user_id' => $member->id,
            'task_id' => $task->id,
            'team_id' => $team->id,
            'log_date' => '2026-07-10',
            'minutes' => 30,
            'progress' => 40,
        ]);
        $latestLog = JournalLog::create([
            'user_id' => $member->id,
            'task_id' => $task->id,
            'team_id' => $team->id,
            'log_date' => '2026-07-11',
            'minutes' => 30,
            'progress' => 100,
        ]);
        TaskMemberProgress::create([
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'done',
            'progress' => 100,
            'started_at' => '2026-07-10 00:00:00',
            'completed_at' => '2026-07-11 12:00:00',
        ]);
        $task->update(['status' => 'done']);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberJournal::class)->call('deleteLog', $latestLog->id);

        $this->assertDatabaseMissing('journal_logs', ['id' => $latestLog->id]);
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'in_progress',
            'progress' => 40,
        ]);
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_logging_more_work_while_in_review_keeps_the_review_status(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Review Status Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Review Status Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);
        $task = $this->createTask('Reviewable task', $project, $team, $lead, $member);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberJournal::class)
            ->set('selectedTaskId', (string) $task->id)
            ->set('logDate', '2026-07-14')
            ->set('minutes', 30)
            ->set('progress', 60)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(MemberDashboard::class)->call('setStatus', $task->id, 'review');

        $this->assertSame('review', $task->fresh()->status);

        // Logging a follow-up entry (e.g. addressing reviewer feedback) should
        // not silently pull the task back out of review.
        Livewire::test(MemberJournal::class)
            ->set('selectedTaskId', (string) $task->id)
            ->set('logDate', '2026-07-15')
            ->set('minutes', 15)
            ->set('progress', 65)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('review', $task->fresh()->status);
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'review',
            'progress' => 65,
        ]);
    }

    public function test_reopening_a_done_task_resets_progress_from_journal_history(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Reopen Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Reopen Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);
        $task = $this->createTask('Reopenable task', $project, $team, $lead, $member);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberJournal::class)
            ->set('selectedTaskId', (string) $task->id)
            ->set('logDate', '2026-07-14')
            ->set('minutes', 30)
            ->set('progress', 40)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(MemberDashboard::class)->call('setStatus', $task->id, 'done');
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'done',
            'progress' => 100,
        ]);

        Livewire::test(MemberDashboard::class)->call('setStatus', $task->id, 'pending');

        $this->assertSame('pending', $task->fresh()->status);
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'progress' => 40,
        ]);
    }

    public function test_editing_task_assignees_preserves_progress_for_a_temporarily_removed_member(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $memberOne = User::factory()->create(['role' => 'member']);
        $memberTwo = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Assignee Edit Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Assignee Edit Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($memberOne->id, ['role' => 'member']);
        $team->members()->attach($memberTwo->id, ['role' => 'member']);
        $task = Task::create([
            'title' => 'Shared assignee task',
            'project_id' => $project->id,
            'team_id' => $team->id,
            'assigned_to' => $memberOne->id,
            'created_by' => $lead->id,
            'due_date' => '2026-07-20',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $task->assignees()->attach([$memberOne->id, $memberTwo->id]);
        TaskMemberProgress::create([
            'task_id' => $task->id,
            'user_id' => $memberOne->id,
            'status' => 'in_progress',
            'progress' => 75,
            'started_at' => '2026-07-10 00:00:00',
        ]);

        $this->actingAs($lead)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ]);

        // Edit the task and drop memberOne from the assignee list.
        Livewire::test(LeadTaskManager::class)
            ->call('openEdit', $task->id)
            ->set('teamId', $team->id)
            ->set('assignedTo', [(string) $memberTwo->id])
            ->set('dueDate', '2026-07-20')
            ->call('save')
            ->assertHasNoErrors();

        // Progress history survives even though memberOne is no longer assigned.
        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $memberOne->id,
            'progress' => 75,
        ]);

        // Re-add memberOne; their historical progress should still be there,
        // not reset back to 0%/pending.
        Livewire::test(LeadTaskManager::class)
            ->call('openEdit', $task->id)
            ->set('teamId', $team->id)
            ->set('assignedTo', [(string) $memberOne->id, (string) $memberTwo->id])
            ->set('dueDate', '2026-07-20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('task_member_progress', [
            'task_id' => $task->id,
            'user_id' => $memberOne->id,
            'status' => 'in_progress',
            'progress' => 75,
        ]);
    }

    public function test_member_cannot_log_work_for_a_future_date(): void
    {
        $this->travelTo(now()->startOfDay());

        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Date Validation Project',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Date Validation Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);
        $task = $this->createTask('Date validation task', $project, $team, $lead, $member);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberJournal::class)
            ->set('selectedTaskId', (string) $task->id)
            ->set('logDate', now()->addDay()->toDateString())
            ->set('minutes', 15)
            ->set('progress', 10)
            ->call('save')
            ->assertHasErrors(['logDate' => 'before_or_equal']);

        $this->assertDatabaseCount('journal_logs', 0);
    }

    private function createTask(string $title, Project $project, Team $team, User $lead, User $member): Task
    {
        return Task::create([
            'title' => $title,
            'project_id' => $project->id,
            'team_id' => $team->id,
            'assigned_to' => $member->id,
            'created_by' => $lead->id,
            'start_date' => '2026-07-01',
            'due_date' => '2026-07-31',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
    }
}
