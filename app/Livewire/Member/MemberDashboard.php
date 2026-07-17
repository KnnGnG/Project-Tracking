<?php

namespace App\Livewire\Member;

use App\Models\InAppNotification;
use App\Models\JournalLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Tasks')]
class MemberDashboard extends Component
{
    #[On('journal-log-changed')]
    public function refreshJournalLinkedData(): void
    {
        // Listener intentionally empty; Livewire rerenders after the event action.
    }

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

    /** Task opened from a notification link. */
    #[Url(as: 'task')]
    public ?int $focusTaskId = null;

    /** Flash message */
    public ?string $flash = null;

    public function mount(): void
    {
        $this->filterTeam = request()->has('team')
            ? request()->integer('team')
            : (int) session('active_team_id', 0);
        $this->filterProject = request()->has('project')
            ? request()->integer('project')
            : (int) session('active_project_id', 0);

        $this->normalizeAccessibleFilters();

        if ($this->focusTaskId) {
            $this->openFocusTask($this->focusTaskId);
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->expandedTaskId = null;
    }

    public function updatedFilterTeam(): void
    {
        $this->filterTeam = max(0, (int) $this->filterTeam);
        $this->filterProject = 0;
        $this->normalizeAccessibleFilters();
        $this->expandedTaskId = null;
    }

    public function updatedFilterProject(): void
    {
        $this->filterProject = max(0, (int) $this->filterProject);
        $this->normalizeAccessibleFilters();
        $this->expandedTaskId = null;
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedTaskId = ($this->expandedTaskId === $id) ? null : $id;
    }

    public function downloadAttachment(int $id): StreamedResponse
    {
        $attachment = TaskAttachment::findOrFail($id);
        abort_unless($this->ownedTask($attachment->task_id), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function openFocusTask(int $id): void
    {
        $task = $this->ownedTask($id)?->load('memberProgress');

        if (! $task) {
            return;
        }

        $this->filterTeam = (int) ($task->team_id ?? 0);
        $this->filterProject = (int) ($task->project_id ?? 0);
        $this->normalizeAccessibleFilters();

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

        $this->updateMemberProgress($task, $newStatus);
        // Overall task status is derived from every assignee's progress below.
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

    private function normalizeAccessibleFilters(): void
    {
        $userId = auth()->id();
        $activeProjectId = (int) session('active_project_id', 0);

        if ($activeProjectId > 0 && $this->filterProject < 1) {
            $this->filterProject = $activeProjectId;
        }

        if ($this->filterTeam > 0) {
            $teamIsAccessible = Team::query()
                ->whereKey($this->filterTeam)
                ->when($this->filterProject > 0, fn ($query) => $query->assignedToProject($this->filterProject))
                ->where(function ($query) use ($userId) {
                    $query->whereHas('members', fn ($members) => $members
                        ->whereKey($userId)
                        ->where('team_members.role', 'member'))
                        ->orWhereHas('tasks', function ($tasks) use ($userId) {
                            $tasks->when($this->filterProject > 0, fn ($taskScope) => $taskScope->where('project_id', $this->filterProject))
                                ->where(function ($taskScope) use ($userId) {
                                    $taskScope->where('assigned_to', $userId)
                                        ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId));
                                });
                        });
                })
                ->exists();

            if (! $teamIsAccessible) {
                $this->filterTeam = 0;
            }
        }

        if ($this->filterProject > 0) {
            $projectIsAccessible = Team::query()
                ->assignedToProject($this->filterProject)
                ->whereHas('members', fn ($members) => $members
                    ->whereKey($userId)
                    ->where('team_members.role', 'member'))
                ->when($this->filterTeam > 0, fn ($query) => $query->whereKey($this->filterTeam))
                ->exists()
                || Task::query()
                    ->where('project_id', $this->filterProject)
                    ->where(function ($query) use ($userId) {
                        $query->where('assigned_to', $userId)
                            ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId));
                    })
                    ->when($this->filterTeam > 0, fn ($query) => $query->where('team_id', $this->filterTeam))
                    ->exists();

            if (! $projectIsAccessible) {
                $this->filterProject = 0;
            }
        }
    }

    private function ownedTask(int $id): ?Task
    {
        return Task::where('id', $id)
            ->where(fn ($q) => $q
                ->where('assigned_to', auth()->id())
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey(auth()->id())))
            ->first();
    }

    private function latestJournalProgress(int $taskId): ?int
    {
        return JournalLog::where('task_id', $taskId)
            ->where('user_id', auth()->id())
            ->whereNotNull('progress')
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->value('progress');
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
                    'body' => $task->title.' was marked done.',
                    'url' => $this->taskCompletionNotificationUrl($task, $userId),
                    'data' => ['task_id' => $task->id, 'team_id' => $task->team_id],
                ]);
            });
    }

    private function taskCompletionNotificationUrl(Task $task, int $userId): string
    {
        $recipient = User::find($userId);

        if ($recipient && $task->team_id && $recipient->ledTeams()->whereKey($task->team_id)->exists()) {
            return route('lead.tasks', ['team' => $task->team_id]);
        }

        return route('dashboard');
    }

    private function updateMemberProgress(Task $task, string $status): void
    {
        $progress = TaskMemberProgress::firstOrNew(
            ['task_id' => $task->id, 'user_id' => auth()->id()]
        );

        $progress->status = $status;

        // Journal logs are the source of truth for the progress percentage; a
        // status change alone should not overwrite what the member has actually
        // reported. "Done" is the one exception, since it means fully complete,
        // and "pending" (e.g. reopening a done task) resets to what the journal
        // actually shows instead of leaving a stale percentage behind.
        if ($status === 'done') {
            $progress->progress = 100;
        } elseif ($status === 'pending') {
            $progress->progress = $this->latestJournalProgress($task->id) ?? 0;
        } elseif (! $progress->exists) {
            $progress->progress = 0;
        }

        if (in_array($status, ['in_progress', 'review', 'done'], true) && ! $progress->started_at) {
            $progress->started_at = now();
        }

        $progress->completed_at = $status === 'done' ? now() : null;
        $progress->save();
    }

    private function syncOverallTaskStatus(Task $task): void
    {
        $progress = $task->activeMemberProgress();

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

        $task->update([
            'status' => $status,
            'completed_at' => $status === 'done' ? ($task->completed_at ?? now()) : null,
        ]);
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
            'description' => auth()->user()->name.' changed status from '.str_replace('_', ' ', $oldStatus).' to '.str_replace('_', ' ', $newStatus).'.',
            'data' => ['old_status' => $oldStatus, 'new_status' => $newStatus],
        ]);
    }

    public function render()
    {
        $this->normalizeAccessibleFilters();

        $userId = auth()->id();
        $today = now()->toDateString();
        $activeProjectId = (int) session('active_project_id', 0);

        $teams = Team::query()
            ->with(['project', 'projects'])
            ->when($this->filterProject > 0, fn ($query) => $query->assignedToProject($this->filterProject))
            ->where(fn ($query) => $query
                ->whereHas('members', fn ($members) => $members
                    ->whereKey($userId)
                    ->where('team_members.role', 'member'))
                ->orWhereHas('tasks', fn ($tasks) => $tasks
                    ->where(fn ($taskScope) => $taskScope
                        ->where('assigned_to', $userId)
                        ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
                    ->when($this->filterProject > 0, fn ($taskScope) => $taskScope->where('project_id', $this->filterProject))))
            ->orderBy('name')
            ->get();

        if ($this->filterTeam > 0 && ! $teams->contains('id', $this->filterTeam)) {
            $this->filterTeam = 0;
        }

        // ── Gather projects this member has tasks in (for filter dropdown) ──────
        $projects = Project::where(fn ($project) => $project
            ->whereHas('teams', fn ($teams) => $teams
                ->when($this->filterTeam > 0, fn ($teamQuery) => $teamQuery->whereKey($this->filterTeam))
                ->whereHas('members', fn ($members) => $members
                    ->whereKey($userId)
                    ->where('team_members.role', 'member')))
            ->orWhereHas('tasks', function ($q) use ($userId) {
                $q->where(function ($inner) use ($userId) {
                    $inner->where('assigned_to', $userId)
                        ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId));
                });
                if ($this->filterTeam > 0) {
                    $q->where('team_id', $this->filterTeam);
                }
            }))
            ->when($activeProjectId > 0, fn ($query) => $query->whereKey($activeProjectId))
            ->orderBy('name')
            ->get(['id', 'name']);

        // ── Base query ───────────────────────────────────────────────────────────
        $base = Task::with(['project', 'team', 'memberProgress.user', 'attachments'])
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
