<?php

namespace App\Livewire\Lead;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use App\Models\InAppNotification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Manage Tasks')]
class LeadTaskManager extends Component
{
    // ── Filter state ─────────────────────────────────────────────────────────
    public ?int $filterTeamId = null;

    public string $filterStatus = '';

    // ── Task form state ───────────────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public ?int $teamId = null;

    public array $assignedTo = [];

    public string $startDate = '';

    public string $dueDate = '';

    public string $status = 'pending';

    public string $priority = 'medium';

    public string $memberSearch = '';

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;
    public ?int $expandedTaskId = null;

    public function mount(): void
    {
        // Default filter to first led team
        $requestedTeamId = request()->has('team')
            ? request()->integer('team')
            : session('active_team_id');

        $first = auth()->user()
            ->ledTeams()
            ->when($requestedTeamId, fn ($query) => $query->whereKey($requestedTeamId))
            ->first()
            ?? auth()->user()->ledTeams()->first();

        if ($first) {
            $this->filterTeamId = $first->id;
        }
    }

    // ── Form open/close ───────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        // Pre-fill team from current filter
        $this->teamId = $this->filterTeamId;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $task = $this->ownedTask($id);

        $this->editingId = $id;
        $this->title = $task->title;
        $this->description = $task->description ?? '';
        $this->teamId = $task->team_id;
        $this->assignedTo = collect([$task->assigned_to])
            ->filter()
            ->merge($task->assignees()->orderBy('users.name')->pluck('users.id'))
            ->unique()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
        $this->startDate = $task->start_date?->toDateString() ?? '';
        $this->dueDate = $task->due_date->toDateString();
        $this->status = $task->status;
        $this->priority = $task->priority;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    // ── Reactive cascades ─────────────────────────────────────────────────────

    /** When team changes, reset the assignee so the dropdown refreshes. */
    public function updatedTeamId(): void
    {
        $this->assignedTo = [];
        $this->memberSearch = '';
    }

    // ── Save / Delete ─────────────────────────────────────────────────────────

    public function save(): void
    {
        $data = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'teamId' => 'required|exists:teams,id',
            'assignedTo' => 'required|array|min:1',
            'assignedTo.*' => 'integer|exists:users,id',
            'startDate' => 'nullable|date',
            'dueDate' => 'required|date',
            'status' => 'required|in:pending,in_progress,review,done',
            'priority' => 'required|in:low,medium,high',
        ]);

        // Ensure the team belongs to this lead
        $team = $this->ownedTeam($data['teamId']);
        $assigneeIds = collect($data['assignedTo'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $validMemberIds = $team->members()->whereIn('users.id', $assigneeIds)->pluck('users.id');

        if ($validMemberIds->count() !== $assigneeIds->count()) {
            $this->addError('assignedTo', 'Choose members from the selected team.');

            return;
        }

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'],
            'project_id' => $team->project_id,
            'team_id' => $team->id,
            'assigned_to' => $assigneeIds->first(),
            'start_date' => $this->editingId ? ($data['startDate'] ?: null) : null,
            'due_date' => $data['dueDate'],
            'status' => $data['status'],
            'priority' => $data['priority'],
        ];

        if ($this->editingId) {
            $existingTask = $this->ownedTask($this->editingId);
            $oldDueDate = $existingTask->due_date?->toDateString();
            $oldStatus = $existingTask->status;
            $previousAssigneeIds = $existingTask
                ->assignees()
                ->pluck('users.id')
                ->push($existingTask->assigned_to)
                ->filter()
                ->unique();

            DB::transaction(function () use ($payload, $assigneeIds, $oldDueDate, $oldStatus, $previousAssigneeIds) {
                $task = $this->ownedTask($this->editingId);
                $statusChanged = $task->status !== $payload['status'];
                $task->update($payload);
                $task->assignees()->sync($assigneeIds->all());
                $this->syncMemberProgressRows($task, $assigneeIds, $statusChanged ? $payload['status'] : null);

                if ($oldDueDate !== $payload['due_date']) {
                    $this->recordActivity($task, 'due_date_changed', auth()->user()->name . ' changed due date from ' . ($oldDueDate ?: 'none') . ' to ' . $payload['due_date'] . '.');
                }

                if ($statusChanged) {
                    $this->recordActivity($task, 'status_changed', auth()->user()->name . ' changed status from ' . str_replace('_', ' ', $oldStatus) . ' to ' . str_replace('_', ' ', $payload['status']) . '.');
                }

                $this->notifyAssignedMembers($task, $assigneeIds->diff($previousAssigneeIds));
            });
            session()->flash('success', 'Task updated.');
        } else {
            DB::transaction(function () use ($payload, $assigneeIds) {
                $task = Task::create(array_merge($payload, ['created_by' => auth()->id()]));
                $task->assignees()->sync($assigneeIds->all());
                $this->syncMemberProgressRows($task, $assigneeIds, $payload['status']);
                $this->recordActivity($task, 'created', auth()->user()->name . ' assigned this task.');
                $this->notifyAssignedMembers($task, $assigneeIds);
            });

            session()->flash('success', 'Task created and assigned.');
        }

        $this->refreshActiveSelfAssignedTaskContext();
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $this->ownedTask($id)->delete();
        $this->refreshActiveSelfAssignedTaskContext();
        session()->flash('success', 'Task deleted.');
    }

    public function confirmDelete(int $id): void
    {
        $this->ownedTask($id);
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deleteId) {
            $this->delete($this->deleteId);
        }

        $this->cancelDelete();
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function updateStatus(int $id, string $status): void
    {
        if (! in_array($status, ['pending', 'in_progress', 'review', 'done'], true)) {
            return;
        }

        $this->ownedTask($id)->update(['status' => $status]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function toggleTaskDetails(int $id): void
    {
        $this->ownedTask($id);
        $this->expandedTaskId = $this->expandedTaskId === $id ? null : $id;
    }

    public function selectAllMembers(): void
    {
        if (! $this->teamId) {
            return;
        }

        $this->assignedTo = $this->membersForSelectedTeam()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function clearMembers(): void
    {
        $this->assignedTo = [];
    }

    /** Returns a task only if it belongs to one of this lead's teams. */
    private function ownedTask(int $id): Task
    {
        $teamIds = auth()->user()->ledTeams()->pluck('id');
        $task = Task::whereIn('team_id', $teamIds)->findOrFail($id);

        return $task;
    }

    /** Returns a team only if this user leads it. */
    private function ownedTeam(int $id): Team
    {
        return auth()->user()->ledTeams()->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = '';
        $this->teamId = null;
        $this->assignedTo = [];
        $this->memberSearch = '';
        $this->startDate = '';
        $this->dueDate = '';
        $this->status = 'pending';
        $this->priority = 'medium';
        $this->resetValidation();
    }

    private function notifyAssignedMembers(Task $task, $assigneeIds): void
    {
        collect($assigneeIds)
            ->filter()
            ->unique()
            ->each(function ($userId) use ($task) {
                InAppNotification::create([
                    'user_id' => $userId,
                    'type' => 'task_assigned',
                    'title' => 'New task assigned',
                    'body' => $task->title,
                    'url' => route('member.dashboard'),
                    'data' => ['task_id' => $task->id],
                ]);
            });
    }

    private function syncMemberProgressRows(Task $task, $assigneeIds, ?string $status = null): void
    {
        TaskMemberProgress::where('task_id', $task->id)
            ->whereNotIn('user_id', $assigneeIds)
            ->delete();

        foreach ($assigneeIds as $userId) {
            $progress = TaskMemberProgress::firstOrCreate(
                ['task_id' => $task->id, 'user_id' => $userId],
                ['status' => 'pending', 'progress' => 0]
            );

            if ($status) {
                $progress->status = $status;
                $progress->progress = $this->progressValueForStatus($status);
                if (in_array($status, ['in_progress', 'review', 'done'], true) && ! $progress->started_at) {
                    $progress->started_at = now();
                }
                $progress->completed_at = $status === 'done' ? now() : null;
                $progress->save();
            }
        }
    }

    private function progressValueForStatus(string $status): int
    {
        return match ($status) {
            'done' => 100,
            'in_progress' => 50,
            default => 0,
        };
    }

    private function recordActivity(Task $task, string $type, string $description): void
    {
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
        ]);
    }

    private function refreshActiveSelfAssignedTaskContext(): void
    {
        $teamId = (int) ($this->filterTeamId ?: session('active_team_id', 0));

        if ($teamId < 1) {
            return;
        }

        $hasSelfAssignedTask = Task::query()
            ->where('team_id', $teamId)
            ->where(fn ($query) => $query
                ->where('assigned_to', auth()->id())
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey(auth()->id())))
            ->exists();

        session([
            'active_team_id' => $teamId,
            'active_has_self_assigned_task' => $hasSelfAssignedTask,
        ]);
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $leadTeams = auth()->user()->ledTeams()->with('project')->get();

        // Tasks visible to this lead — filtered
        $tasks = Task::with(['assignee', 'assignees', 'team', 'project', 'memberProgress.user'])
            ->whereIn('team_id', $leadTeams->pluck('id'))
            ->when($this->filterTeamId, fn ($q) => $q->where('team_id', $this->filterTeamId))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'review' THEN 1 WHEN status = 'pending' THEN 2 WHEN status = 'done' THEN 3 ELSE 4 END")
            ->orderBy('due_date')
            ->get();

        // Members for the team selected in the form
        $membersForForm = $this->membersForSelectedTeam();

        return view('livewire.lead.lead-task-manager',
            compact('leadTeams', 'tasks', 'membersForForm'));
    }

    private function membersForSelectedTeam()
    {
        if (! $this->teamId) {
            return collect();
        }

        return $this->ownedTeam($this->teamId)
            ->members()
            ->when($this->memberSearch, fn ($q) => $q->where('name', 'like', "%{$this->memberSearch}%"))
            ->orderBy('name')
            ->get();
    }
}
