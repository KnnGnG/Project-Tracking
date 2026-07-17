<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTaskAssignmentPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_projects_scopes_task_notification_counts_to_each_project_card(): void
    {
        $this->travelTo('2026-07-14 10:00:00');

        [$member, $task] = $this->assignmentContext();
        $this->taskNotification($member, $task);
        Task::create([
            'title' => 'Past due assignment',
            'project_id' => $task->project_id,
            'team_id' => $task->team_id,
            'assigned_to' => $member->id,
            'created_by' => $task->created_by,
            'start_date' => '2026-07-01',
            'due_date' => '2026-07-10',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $otherProject = Project::create([
            'name' => 'Second Popup Project',
            'description' => 'Another test project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $task->created_by,
        ]);
        $otherTeam = Team::create([
            'name' => 'Second Popup Team',
            'project_id' => $otherProject->id,
            'lead_id' => $task->created_by,
        ]);
        $otherTeam->members()->attach($member->id, ['role' => 'member']);

        foreach (['Second project task', 'Another second project task'] as $title) {
            $otherTask = Task::create([
                'title' => $title,
                'project_id' => $otherProject->id,
                'team_id' => $otherTeam->id,
                'assigned_to' => $member->id,
                'created_by' => $task->created_by,
                'start_date' => '2026-07-14',
                'due_date' => '2026-07-25',
                'status' => 'pending',
                'priority' => 'medium',
            ]);
            $this->taskNotification($member, $otherTask);
        }

        $response = $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('css/project-notifications.css?v=', false)
            ->assertSee('project-task-count-overdue', false);

        $firstCard = $this->projectCardHtml($response->getContent(), $task->project_id);
        $secondCard = $this->projectCardHtml($response->getContent(), $otherProject->id);

        $this->assertStringContainsString('1 unread', $firstCard);
        $this->assertStringContainsString('New assignments', $firstCard);
        $this->assertStringContainsString('Popup Team', $firstCard);
        $this->assertStringContainsString('Jul 14 - Jul 20, 2026', $firstCard);
        $this->assertDoesNotMatchRegularExpression('/\d+%/', $firstCard);
        $this->assertStringContainsString('Overdue', $firstCard);
        $this->assertStringContainsString('Pending', $firstCard);
        $this->assertLessThan(
            strpos($firstCard, 'project-status-pill'),
            strpos($firstCard, 'project-task-notification-count')
        );
        $this->assertStringContainsString('2 unread', $secondCard);
        $this->assertStringNotContainsString('Overdue', $secondCard);
    }

    public function test_open_task_marks_only_the_owned_assignment_as_read(): void
    {
        [$member, $task] = $this->assignmentContext();
        $notification = $this->taskNotification($member, $task);
        $notification->update(['url' => 'https://example.invalid/unsafe']);

        $this->actingAs($member)
            ->post(route('projects.new-tasks.open', $notification))
            ->assertRedirect(route('member.dashboard', [
                'team' => $task->team_id,
                'project' => $task->project_id,
                'task' => $task->id,
            ]));

        $this->assertNotNull($notification->fresh()->read_at);

        $otherMember = User::factory()->create(['role' => 'member']);
        $this->actingAs($otherMember)
            ->post(route('projects.new-tasks.open', $notification))
            ->assertNotFound();
    }

    public function test_mark_all_read_clears_only_notifications_for_the_selected_project(): void
    {
        [$member, $firstTask] = $this->assignmentContext();
        $firstNotification = $this->taskNotification($member, $firstTask);
        $secondTask = Task::create([
            'title' => 'Second project assignment',
            'project_id' => $firstTask->project_id,
            'team_id' => $firstTask->team_id,
            'assigned_to' => $member->id,
            'created_by' => $firstTask->created_by,
            'start_date' => '2026-07-14',
            'due_date' => '2026-07-25',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $secondNotification = $this->taskNotification($member, $secondTask);

        $otherProject = Project::create([
            'name' => 'Unread Other Project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $firstTask->created_by,
        ]);
        $otherTeam = Team::create([
            'name' => 'Unread Other Team',
            'project_id' => $otherProject->id,
            'lead_id' => $firstTask->created_by,
        ]);
        $otherTeam->members()->attach($member->id, ['role' => 'member']);
        $otherTask = Task::create([
            'title' => 'Keep this notification unread',
            'project_id' => $otherProject->id,
            'team_id' => $otherTeam->id,
            'assigned_to' => $member->id,
            'created_by' => $firstTask->created_by,
            'start_date' => '2026-07-14',
            'due_date' => '2026-07-25',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $otherNotification = $this->taskNotification($member, $otherTask);

        $outsider = User::factory()->create(['role' => 'member']);
        $this->actingAs($outsider)
            ->post(route('projects.new-tasks.read-all', $firstTask->project_id))
            ->assertNotFound();

        $this->actingAs($member)
            ->post(route('projects.new-tasks.read-all', $firstTask->project_id))
            ->assertRedirect(route('projects.index'));

        $this->assertNotNull($firstNotification->fresh()->read_at);
        $this->assertNotNull($secondNotification->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_member_can_dismiss_one_owned_task_notification_without_clearing_the_rest(): void
    {
        [$member, $firstTask] = $this->assignmentContext();
        $firstNotification = $this->taskNotification($member, $firstTask);
        $secondTask = Task::create([
            'title' => 'Keep this assignment visible',
            'project_id' => $firstTask->project_id,
            'team_id' => $firstTask->team_id,
            'assigned_to' => $member->id,
            'created_by' => $firstTask->created_by,
            'start_date' => '2026-07-14',
            'due_date' => '2026-07-25',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $secondNotification = $this->taskNotification($member, $secondTask);

        $this->actingAs($member)
            ->post(route('projects.new-tasks.dismiss', $firstNotification))
            ->assertRedirect(route('projects.index'));

        $this->assertNotNull($firstNotification->fresh()->read_at);
        $this->assertNull($secondNotification->fresh()->read_at);

        $outsider = User::factory()->create(['role' => 'member']);
        $this->actingAs($outsider)
            ->post(route('projects.new-tasks.dismiss', $secondNotification))
            ->assertNotFound();
    }

    public function test_project_cards_show_date_aware_color_coded_statuses(): void
    {
        Carbon::setTestNow('2026-07-14 10:00:00');

        try {
            $lead = User::factory()->create(['role' => 'team_lead']);
            $member = User::factory()->create(['role' => 'member']);
            $statuses = [
                ['Overdue Project', '2026-07-01', '2026-07-13', 'active', 'overdue', 'Overdue'],
                ['Near Due Project', '2026-07-01', '2026-07-18', 'active', 'near-due', 'Near Due'],
                ['Upcoming Project', '2026-07-20', '2026-07-31', 'active', 'upcoming', 'Upcoming'],
                ['Completed Project', '2026-06-01', '2026-06-30', 'completed', 'completed', 'Completed'],
            ];

            foreach ($statuses as [$name, $start, $end, $status, $className]) {
                $project = Project::create([
                    'name' => $name,
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => $status,
                    'created_by' => $lead->id,
                ]);
                $team = Team::create([
                    'name' => $name.' Team',
                    'project_id' => $project->id,
                    'lead_id' => $lead->id,
                ]);
                $team->members()->attach($member->id, ['role' => 'member']);
            }

            $response = $this->actingAs($member)->get(route('projects.index'))->assertOk();

            foreach ($statuses as [$name, , , , $className, $label]) {
                $response
                    ->assertSee($name)
                    ->assertSee('project-card-state-'.$className, false)
                    ->assertSee('Project status: '.$label, false);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_my_projects_can_be_filtered_by_status_and_sorted(): void
    {
        $this->travelTo('2026-07-14 10:00:00');

        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $statuses = [
            ['Overdue Project', '2026-07-01', '2026-07-13', 'active', 'overdue'],
            ['Near Due Project', '2026-07-01', '2026-07-18', 'active', 'near_due'],
            ['Upcoming Project', '2026-07-20', '2026-07-31', 'active', 'upcoming'],
            ['Completed Project', '2026-06-01', '2026-06-30', 'completed', 'completed'],
        ];

        foreach ($statuses as [$name, $start, $end, $status]) {
            $project = Project::create([
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
                'status' => $status,
                'created_by' => $lead->id,
            ]);
            $team = Team::create([
                'name' => $name.' Team',
                'project_id' => $project->id,
                'lead_id' => $lead->id,
            ]);
            $team->members()->attach($member->id, ['role' => 'member']);
        }

        // Filtering by status only shows the matching project.
        $filtered = $this->actingAs($member)
            ->get(route('projects.index', ['status' => 'overdue']))
            ->assertOk();
        $filtered->assertSee('Overdue Project');
        foreach (['Near Due Project', 'Upcoming Project', 'Completed Project'] as $hidden) {
            $filtered->assertDontSee($hidden);
        }

        $filteredCompleted = $this->actingAs($member)
            ->get(route('projects.index', ['status' => 'completed']))
            ->assertOk();
        $filteredCompleted->assertSee('Completed Project');
        foreach (['Overdue Project', 'Near Due Project', 'Upcoming Project'] as $hidden) {
            $filteredCompleted->assertDontSee($hidden);
        }

        // Sorting by end date orders projects from soonest to latest end date.
        $this->actingAs($member)
            ->get(route('projects.index', ['sort' => 'end_date']))
            ->assertOk()
            ->assertSeeInOrder(['Completed Project', 'Overdue Project', 'Near Due Project', 'Upcoming Project']);

        // Default sort is alphabetical by name.
        $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSeeInOrder(['Completed Project', 'Near Due Project', 'Overdue Project', 'Upcoming Project']);

        // An unrecognized status value falls back to showing everything.
        $invalidFilter = $this->actingAs($member)
            ->get(route('projects.index', ['status' => 'not-a-real-status']))
            ->assertOk();
        foreach (['Overdue Project', 'Near Due Project', 'Upcoming Project', 'Completed Project'] as $name) {
            $invalidFilter->assertSee($name);
        }
    }

    public function test_project_card_counts_are_not_truncated_after_twenty_five_tasks(): void
    {
        [$member, $firstTask] = $this->assignmentContext();
        $this->taskNotification($member, $firstTask);

        foreach (range(2, 26) as $number) {
            $task = Task::create([
                'title' => "Scale task {$number}",
                'project_id' => $firstTask->project_id,
                'team_id' => $firstTask->team_id,
                'assigned_to' => $member->id,
                'created_by' => $firstTask->created_by,
                'start_date' => '2026-07-14',
                'due_date' => '2026-07-30',
                'status' => 'pending',
                'priority' => 'medium',
            ]);
            $this->taskNotification($member, $task);
        }

        $response = $this->actingAs($member)->get(route('projects.index'))->assertOk();
        $card = $this->projectCardHtml($response->getContent(), $firstTask->project_id);

        $this->assertStringContainsString('26 unread', $card);
        $this->assertStringContainsString('<strong>26</strong>', $card);
    }

    private function assignmentContext(): array
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Popup Project',
            'description' => 'Test project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Popup Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);

        $task = Task::create([
            'title' => 'Review the new assignment',
            'project_id' => $project->id,
            'team_id' => $team->id,
            'assigned_to' => $member->id,
            'created_by' => $lead->id,
            'start_date' => '2026-07-14',
            'due_date' => '2026-07-20',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        return [$member, $task];
    }

    private function taskNotification(User $member, Task $task): InAppNotification
    {
        return InAppNotification::create([
            'user_id' => $member->id,
            'type' => 'task_assigned',
            'title' => 'New task assigned',
            'body' => $task->title,
            'url' => route('member.dashboard', [
                'team' => $task->team_id,
                'project' => $task->project_id,
                'task' => $task->id,
            ]),
            'data' => [
                'task_id' => $task->id,
                'team_id' => $task->team_id,
                'project_id' => $task->project_id,
            ],
        ]);
    }

    private function projectCardHtml(string $html, int $projectId): string
    {
        preg_match(
            '/<article class="project-picker-card [^"]*" data-project-id="'.preg_quote((string) $projectId, '/').'">(.*?)<\/article>/s',
            $html,
            $matches
        );

        $this->assertArrayHasKey(1, $matches, "Project card {$projectId} was not rendered.");

        return $matches[1];
    }
}
