<?php

namespace App\Livewire\Lead;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use App\Livewire\Concerns\ResolvesLeadProjectContext;
use App\Models\InAppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Manage Tasks')]
class LeadTaskManager extends Component
{
    use ResolvesLeadProjectContext, WithFileUploads;

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

    public string $startTime = '';

    public string $dueDate = '';

    public string $status = 'pending';

    public string $priority = 'medium';

    public string $memberSearch = '';

    public array $newAttachments = [];

    public int $uploadIteration = 0;

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;
    public ?int $expandedTaskId = null;

    public function mount(): void
    {
        // Default filter to first led team
        $requestedTeamId = request()->has('team')
            ? request()->integer('team')
            : session('active_team_id');

        $leadTeams = $this->leadTeams();
        $first = $requestedTeamId
            ? $leadTeams->firstWhere('id', $requestedTeamId)
            : null;
        $first ??= $leadTeams->first();

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
        $this->startTime = $task->start_time ? substr((string) $task->start_time, 0, 5) : '';
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
            'startTime' => 'nullable|date_format:H:i',
            'dueDate' => 'required|date',
            'priority' => 'required|in:low,medium,high',
            'newAttachments' => 'array|max:5',
            'newAttachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp,zip',
        ]);

        if ($data['startTime'] && ! $data['startDate']) {
            $this->addError('startDate', 'Choose a scheduled start date when setting a start time.');

            return;
        }

        if ($data['startDate'] && $data['dueDate'] < $data['startDate']) {
            $this->addError('dueDate', 'The due date must be on or after the scheduled start date.');

            return;
        }

        // Ensure the team belongs to this lead
        $team = $this->ownedTeam($data['teamId']);
        $project = $this->activeProjectForTeam($team);

        if (! $project) {
            $this->addError('teamId', 'Choose a team from the selected project.');

            return;
        }

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
            'project_id' => $project->id,
            'team_id' => $team->id,
            'assigned_to' => $assigneeIds->first(),
            'start_date' => $data['startDate'] ?: null,
            'start_time' => $data['startTime'] ?: null,
            'due_date' => $data['dueDate'],
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

            $task = DB::transaction(function () use ($payload, $assigneeIds, $oldDueDate, $oldStatus, $previousAssigneeIds) {
                $task = $this->ownedTask($this->editingId);
                $task->update($payload);
                $task->assignees()->sync($assigneeIds->all());
                $this->syncMemberProgressRows($task, $assigneeIds);
                $derivedStatus = $this->syncOverallTaskStatus($task->fresh(['memberProgress']));

                if ($oldDueDate !== $payload['due_date']) {
                    $this->recordActivity($task, 'due_date_changed', auth()->user()->name . ' changed due date from ' . ($oldDueDate ?: 'none') . ' to ' . $payload['due_date'] . '.');
                }

                if ($oldStatus !== $derivedStatus) {
                    $this->recordActivity($task, 'status_changed', 'Task status updated from ' . str_replace('_', ' ', $oldStatus) . ' to ' . str_replace('_', ' ', $derivedStatus) . ' based on member progress.');
                }

                $this->notifyAssignedMembers($task, $assigneeIds->diff($previousAssigneeIds));

                return $task;
            });
            $this->storeAttachments($task);
            session()->flash('success', 'Task updated.');
        } else {
            $task = DB::transaction(function () use ($payload, $assigneeIds) {
                $task = Task::create(array_merge($payload, ['created_by' => auth()->id(), 'status' => 'pending']));
                $task->assignees()->sync($assigneeIds->all());
                $this->syncMemberProgressRows($task, $assigneeIds);
                $this->syncOverallTaskStatus($task->fresh(['memberProgress']));
                $this->recordActivity($task, 'created', auth()->user()->name . ' assigned this task.');
                $this->notifyAssignedMembers($task, $assigneeIds);

                return $task;
            });
            $this->editingId = $task->id;
            $this->storeAttachments($task);

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

        $task = $this->ownedTask($id);
        $oldStatus = $task->status;

        DB::transaction(function () use ($task, $oldStatus, $status): void {
            $assigneeIds = $task->assignees()
                ->pluck('users.id')
                ->push($task->assigned_to)
                ->filter()
                ->unique()
                ->values();

            $this->syncMemberProgressRows($task, $assigneeIds, $status);
            $derivedStatus = $this->syncOverallTaskStatus($task->fresh(['memberProgress']));

            if ($oldStatus !== $derivedStatus) {
                $this->recordActivity($task, 'status_changed', 'Task status refreshed from ' . str_replace('_', ' ', $oldStatus) . ' to ' . str_replace('_', ' ', $derivedStatus) . ' based on member progress.');
            }
        });

        $this->refreshActiveSelfAssignedTaskContext();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function toggleTaskDetails(int $id): void
    {
        $this->ownedTask($id);
        $this->expandedTaskId = $this->expandedTaskId === $id ? null : $id;
    }

    public function removePendingAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->newAttachments)) {
            return;
        }

        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
        $this->resetValidation();
    }

    public function removeAttachment(int $id): void
    {
        $attachment = TaskAttachment::findOrFail($id);
        $this->ownedTask($attachment->task_id);
        $attachment->delete();

        session()->flash('success', 'Attachment removed.');
    }

    public function downloadAttachment(int $id)
    {
        $attachment = TaskAttachment::findOrFail($id);
        $this->ownedTask($attachment->task_id);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
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
        $teamIds = $this->leadTeams()->pluck('id');
        $task = Task::whereIn('team_id', $teamIds)
            ->when((int) session('active_project_id', 0) > 0, fn ($q) => $q->where('project_id', (int) session('active_project_id')))
            ->findOrFail($id);

        return $task;
    }

    /** Returns a team only if this user leads it. */
    private function ownedTeam(int $id): Team
    {
        return $this->leadTeams()->firstWhere('id', $id) ?? abort(404);
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
        $this->startTime = '';
        $this->dueDate = '';
        $this->status = 'pending';
        $this->priority = 'medium';
        $this->newAttachments = [];
        $this->uploadIteration++;
        $this->resetValidation();
    }

    private function storeAttachments(Task $task): void
    {
        foreach ($this->newAttachments as $file) {
            $path = $file->store("task-attachments/{$task->id}", 'local');

            try {
                $task->attachments()->create([
                    'uploaded_by' => auth()->id(),
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        }
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
                    'url' => route('member.dashboard', array_filter([
                        'team' => $task->team_id,
                        'project' => $task->project_id,
                        'task' => $task->id,
                    ])),
                    'data' => ['task_id' => $task->id, 'team_id' => $task->team_id, 'project_id' => $task->project_id],
                ]);
            });
    }

    private function syncMemberProgressRows(Task $task, $assigneeIds, ?string $status = null): void
    {
        // Progress rows for members no longer assigned are kept (not deleted) so
        // their logged time/progress history survives a temporary unassignment;
        // derivedStatusFor() excludes them from the task's overall status via
        // Task::activeMemberProgress().
        foreach ($assigneeIds as $userId) {
            $progress = TaskMemberProgress::firstOrCreate(
                ['task_id' => $task->id, 'user_id' => $userId],
                ['status' => 'pending', 'progress' => 0]
            );

            if ($status) {
                $progress->status = $status;

                // Journal logs are the source of truth for the progress percentage;
                // a lead-driven status change alone should not overwrite what the
                // member has actually reported. "Done" is the one exception, since
                // it means fully complete.
                if ($status === 'done') {
                    $progress->progress = 100;
                }

                if (in_array($status, ['in_progress', 'review', 'done'], true) && ! $progress->started_at) {
                    $progress->started_at = now();
                }
                $progress->completed_at = $status === 'done' ? now() : null;
                $progress->save();
            }
        }
    }

    private function syncOverallTaskStatus(Task $task): string
    {
        $status = $this->derivedStatusFor($task);

        if ($task->status !== $status) {
            $task->update([
                'status' => $status,
                'completed_at' => $status === 'done' ? ($task->completed_at ?? now()) : null,
            ]);
        }

        return $status;
    }

    private function derivedStatusFor(Task $task): string
    {
        $progress = $task->activeMemberProgress();

        if ($progress->isEmpty()) {
            return $task->status;
        }

        return match (true) {
            $progress->every(fn ($item) => $item->status === 'done') => 'done',
            $progress->contains(fn ($item) => $item->status === 'review') => 'review',
            $progress->contains(fn ($item) => in_array($item->status, ['in_progress', 'done'], true)) => 'in_progress',
            $progress->every(fn ($item) => $item->status === 'pending') => 'pending',
            default => 'pending',
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
        $leadTeams = $this->leadTeams();

        if ($this->filterTeamId && ! $leadTeams->contains('id', $this->filterTeamId)) {
            $this->filterTeamId = $leadTeams->first()?->id;
        }

        // Tasks visible to this lead are read-only in render; status is synced when progress changes.
        $tasks = Task::with(['assignee', 'assignees', 'team', 'project', 'memberProgress.user', 'attachments'])
            ->whereIn('team_id', $leadTeams->pluck('id'))
            ->when($this->filterTeamId, fn ($q) => $q->where('team_id', $this->filterTeamId))
            ->when((int) session('active_project_id', 0) > 0, fn ($q) => $q->where('project_id', (int) session('active_project_id')))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'review' THEN 1 WHEN status = 'pending' THEN 2 WHEN status = 'done' THEN 3 ELSE 4 END")
            ->orderBy('due_date')
            ->get();

        // Members for the team selected in the form
        $membersForForm = $this->membersForSelectedTeam();

        $existingAttachments = $this->editingId
            ? $this->ownedTask($this->editingId)->attachments()->latest()->get()
            : collect();

        return view('livewire.lead.lead-task-manager',
            compact('leadTeams', 'tasks', 'membersForForm', 'existingAttachments'));
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
