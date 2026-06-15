<?php

namespace App\Livewire\Member;

use App\Models\Project;
use App\Models\Task;
use App\Models\InAppNotification;
use App\Models\TaskActivity;
use App\Models\TaskMemberProgress;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Tasks')]
class MemberDashboard extends Component
{
    /** Active tab: pending | in_progress | review | exceeded | done */
    #[Url(as: 'tab')]
    public string $activeTab = 'pending';

    /** Filter by project ID (0 = all) */
    #[Url(as: 'project')]
    public int $filterProject = 0;

    /** Filter by team ID (0 = all member teams) */
    #[Url(as: 'team')]
    public int $filterTeam = 0;

    /** Sort field: due_date | priority | title */
    #[Url(as: 'sort')]
    public string $sortBy = 'due_date';

    /** Sort direction */
    #[Url(as: 'dir')]
    public string $sortDir = 'asc';

    /** Currently expanded task ID (null = none) */
    public ?int $expandedTaskId = null;

    /** Flash message */
    public ?string $flash = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->expandedTaskId = null;
    }

    public function updatedFilterTeam(): void
    {
        $this->filterTeam = max(0, (int) $this->filterTeam);
        $this->filterProject = 0;
        $this->expandedTaskId = null;
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedTaskId = ($this->expandedTaskId === $id) ? null : $id;
    }

    public function openFocusTask(int $id): void
    {
        $task = $this->ownedTask($id)?->load('memberProgress');

        if (! $task) {
            return;
        }

        $today = now()->toDateString();
        $personalStatus = $this->personalStatusFor($task);

        $this->activeTab = match (true) {
            $personalStatus === 'done' => 'done',
            $personalStatus !== 'review'
                && $task->due_date
                && $task->due_date->toDateString() < $today => 'exceeded',
            $personalStatus === 'review' => 'review',
            $personalStatus === 'in_progress' => 'in_progress',
            default => 'pending',
        };

        $this->expandedTaskId = $task->id;
    }

    public function setSort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }
    }

    /**
     * Universal status setter – allows full status cycling.
     * Valid transitions a member can initiate:
     *   pending     → in_progress, done
     *   in_progress → pending, done
     *   done        → in_progress, pending
     */
    public function setStatus(int $id, string $newStatus): void
    {
        $allowed = ['pending', 'in_progress', 'review', 'done'];
        if (! in_array($newStatus, $allowed, true)) {
            return;
        }

        $task = $this->ownedTask($id);
        if (! $task) {
            return;
        }

        $oldStatus = $task->status;
        $updates = [];

        // Capture the real start moment: first time a member moves task to in progress.
        if ($newStatus === 'in_progress' && ! $task->start_date) {
            $updates['start_date'] = now()->toDateString();
        }

        $this->updateMemberProgress($task, $newStatus);
        // Overall task status is derived from every assignee's progress below.
        if ($updates !== []) {
            $task->update($updates);
        }
        $this->syncOverallTaskStatus($task->fresh(['memberProgress']));
        $task = $task->fresh(['team']);
        $this->recordStatusActivity($task, $oldStatus, $task->status);

        if ($task->status === 'done' && $oldStatus !== 'done') {
            $this->notifyTaskCompleted($task);
        }

        $this->flash = match ($newStatus) {
            'in_progress' => 'Task marked as In Progress.',
            'review' => 'Task submitted for review.',
            'done' => 'Task marked as Done. Great work!',
            'pending' => 'Task moved back to Pending.',
            default => 'Task updated.',
        };

        // If the task moved out of the current tab, close the detail panel
        $this->expandedTaskId = null;
    }

    private function ownedTask(int $id): ?Task
    {
        return Task::where('id', $id)
            ->where(fn ($q) => $q
                ->where('assigned_to', auth()->id())
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey(auth()->id())))
            ->first();
    }

    public function dismissFlash(): void
    {
        $this->flash = null;
    }

    private function notifyTaskCompleted(Task $task): void
    {
        $leadIds = $task->team
            ? $task->team->leads()->pluck('users.id')->push($task->team->lead_id)
            : collect();

        $leadIds->push($task->created_by)
            ->filter()
            ->unique()
            ->reject(fn ($userId) => $userId === auth()->id())
            ->each(function ($userId) use ($task) {
                InAppNotification::create([
                    'user_id' => $userId,
                    'type' => 'task_completed',
                    'title' => 'Task completed',
                    'body' => $task->title . ' was marked done.',
                    'url' => route('dashboard'),
                    'data' => ['task_id' => $task->id],
                ]);
            });
    }

    private function updateMemberProgress(Task $task, string $status): void
    {
        $progress = TaskMemberProgress::updateOrCreate(
            ['task_id' => $task->id, 'user_id' => auth()->id()],
            [
                'status' => $status,
                'progress' => match ($status) {
                    'done' => 100,
                    'in_progress' => 50,
                    default => 0,
                },
            ]
        );

        if (in_array($status, ['in_progress', 'review', 'done'], true) && ! $progress->started_at) {
            $progress->started_at = now();
        }

        $progress->completed_at = $status === 'done' ? now() : null;
        $progress->save();
    }

    private function syncOverallTaskStatus(Task $task): void
    {
        $progress = $task->memberProgress;

        if ($progress->isEmpty()) {
            return;
        }

        $status = match (true) {
            $progress->every(fn ($item) => $item->status === 'done') => 'done',
            $progress->contains(fn ($item) => $item->status === 'review') => 'review',
            $progress->contains(fn ($item) => $item->status === 'in_progress' || $item->status === 'done') => 'in_progress',
            $progress->every(fn ($item) => $item->status === 'pending') => 'pending',
            default => 'pending',
        };

        $task->update(['status' => $status]);
    }

    private function personalStatusFor(Task $task): string
    {
        return $task->memberProgress
            ->firstWhere('user_id', auth()->id())
            ?->status ?? $task->status;
    }

    private function withPersonalStatus($tasks)
    {
        return $tasks->each(function (Task $task): void {
            $task->setAttribute('personal_status', $this->personalStatusFor($task));
        });
    }

    private function sortTaskCollection($tasks)
    {
        $descending = $this->sortDir === 'desc';

        return match ($this->sortBy) {
            'priority' => $tasks
                ->sortBy(fn (Task $task) => match ($task->priority) {
                    'high' => 0,
                    'medium' => 1,
                    'low' => 2,
                    default => 3,
                }, SORT_REGULAR, $descending)
                ->values(),
            'title' => $tasks
                ->sortBy(fn (Task $task) => strtolower($task->title), SORT_REGULAR, $descending)
                ->values(),
            default => $tasks
                ->sortBy(fn (Task $task) => $task->due_date?->timestamp ?? PHP_INT_MAX, SORT_REGULAR, $descending)
                ->values(),
        };
    }

    private function recordStatusActivity(Task $task, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'type' => 'status_changed',
            'description' => auth()->user()->name . ' changed status from ' . str_replace('_', ' ', $oldStatus) . ' to ' . str_replace('_', ' ', $newStatus) . '.',
            'data' => ['old_status' => $oldStatus, 'new_status' => $newStatus],
        ]);
    }

    public function render()
    {
        $userId = auth()->id();
        $today = now()->toDateString();

        $teams = auth()->user()
            ->memberTeams()
            ->with('project')
            ->orderBy('name')
            ->get();

        if ($this->filterTeam > 0 && ! $teams->contains('id', $this->filterTeam)) {
            $this->filterTeam = 0;
        }

        // ── Gather projects this member has tasks in (for filter dropdown) ──────
        $projects = Project::whereHas('tasks', function ($q) use ($userId) {
            $q->where(function ($inner) use ($userId) {
                $inner->where('assigned_to', $userId)
                    ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId));
            });
            if ($this->filterTeam > 0) {
                $q->where('team_id', $this->filterTeam);
            }
        })
            ->orderBy('name')
            ->get(['id', 'name']);

        // ── Base query ───────────────────────────────────────────────────────────
        $base = Task::with(['project', 'team', 'memberProgress.user'])
            ->where(fn ($q) => $q
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)));

        if ($this->filterProject > 0) {
            $base->where('project_id', $this->filterProject);
        }

        if ($this->filterTeam > 0) {
            $base->where('team_id', $this->filterTeam);
        }

        // ── Per-tab counts (badges) ──────────────────────────────────────────────
        $allTasks = $this->withPersonalStatus($base->get());

        $isOverdueForMember = fn (Task $task): bool => $task->due_date
            && $task->due_date->toDateString() < $today
            && in_array($task->personal_status, ['pending', 'in_progress'], true);

        $focusTasks = $allTasks
            ->filter(fn (Task $task) => in_array($task->personal_status, ['pending', 'in_progress', 'review'], true)
                && (
                    ($task->due_date && $task->due_date->toDateString() <= $today)
                    || in_array($task->personal_status, ['in_progress', 'review'], true)
                ))
            ->sortBy(fn (Task $task) => $task->due_date?->timestamp ?? PHP_INT_MAX)
            ->values();

        $counts = [
            'pending' => $allTasks
                ->filter(fn (Task $task) => $task->personal_status === 'pending'
                    && (! $task->due_date || $task->due_date->toDateString() >= $today))
                ->count(),

            'in_progress' => $allTasks
                ->filter(fn (Task $task) => $task->personal_status === 'in_progress'
                    && (! $task->due_date || $task->due_date->toDateString() >= $today))
                ->count(),

            'review' => $allTasks->where('personal_status', 'review')->count(),

            'exceeded' => $allTasks->filter($isOverdueForMember)->count(),

            'done' => $allTasks->where('personal_status', 'done')->count(),
        ];

        // ── Tasks for the active tab ─────────────────────────────────────────────
        $tasks = match ($this->activeTab) {
            'pending' => $allTasks
                ->filter(fn (Task $task) => $task->personal_status === 'pending'
                    && (! $task->due_date || $task->due_date->toDateString() >= $today)),

            'in_progress' => $allTasks
                ->filter(fn (Task $task) => $task->personal_status === 'in_progress'
                    && (! $task->due_date || $task->due_date->toDateString() >= $today)),

            'review' => $allTasks->where('personal_status', 'review'),

            'exceeded' => $allTasks->filter($isOverdueForMember),

            'done' => $allTasks->where('personal_status', 'done'),

            default => collect(),
        };


        $tasks = $this->sortTaskCollection($tasks);

        return view('livewire.member.member-dashboard', compact('tasks', 'counts', 'projects', 'focusTasks', 'teams'));
    }
}
