<?php

namespace App\Livewire\Lead;

use App\Models\Task;
use App\Models\Team;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Manage Tasks')]
class LeadTaskManager extends Component
{
    // ── Filter state ─────────────────────────────────────────────────────────
    public ?int   $filterTeamId = null;
    public string $filterStatus = '';

    // ── Task form state ───────────────────────────────────────────────────────
    public bool   $showForm       = false;
    public ?int   $editingId      = null;
    public string $title          = '';
    public string $description    = '';
    public ?int   $teamId         = null;
    public ?int   $assignedTo     = null;
    public string $startDate      = '';
    public string $dueDate        = '';
    public string $status         = 'pending';
    public string $priority       = 'medium';

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    public function mount(): void
    {
        // Default filter to first led team
        $first = auth()->user()->ledTeams()->first();
        if ($first) {
            $this->filterTeamId = $first->id;
        }
    }

    // ── Form open/close ───────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        // Pre-fill team from current filter
        $this->teamId   = $this->filterTeamId;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $task = $this->ownedTask($id);

        $this->editingId   = $id;
        $this->title       = $task->title;
        $this->description = $task->description ?? '';
        $this->teamId      = $task->team_id;
        $this->assignedTo  = $task->assigned_to;
        $this->startDate   = $task->start_date?->toDateString() ?? '';
        $this->dueDate     = $task->due_date->toDateString();
        $this->status      = $task->status;
        $this->priority    = $task->priority;
        $this->showForm    = true;
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
        $this->assignedTo = null;
    }

    // ── Save / Delete ─────────────────────────────────────────────────────────

    public function save(): void
    {
        $data = $this->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'teamId'      => 'required|exists:teams,id',
            'assignedTo'  => 'required|exists:users,id',
            'startDate'   => 'nullable|date',
            'dueDate'     => 'required|date',
            'status'      => 'required|in:pending,in_progress,done',
            'priority'    => 'required|in:low,medium,high',
        ]);

        // Ensure the team belongs to this lead
        $team = $this->ownedTeam($data['teamId']);

        $payload = [
            'title'       => $data['title'],
            'description' => $data['description'],
            'project_id'  => $team->project_id,
            'team_id'     => $team->id,
            'assigned_to' => $data['assignedTo'],
            'start_date'  => $this->editingId ? ($data['startDate'] ?: null) : null,
            'due_date'    => $data['dueDate'],
            'status'      => $data['status'],
            'priority'    => $data['priority'],
        ];

        if ($this->editingId) {
            $this->ownedTask($this->editingId)->update($payload);
            session()->flash('success', 'Task updated.');
        } else {
            Task::create(array_merge($payload, ['created_by' => auth()->id()]));
            session()->flash('success', 'Task created and assigned.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $this->ownedTask($id)->delete();
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
        $this->ownedTask($id)->update(['status' => $status]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns a task only if it belongs to one of this lead's teams. */
    private function ownedTask(int $id): Task
    {
        $teamIds = auth()->user()->ledTeams()->pluck('id');
        $task    = Task::whereIn('team_id', $teamIds)->findOrFail($id);
        return $task;
    }

    /** Returns a team only if this user leads it. */
    private function ownedTeam(int $id): Team
    {
        return auth()->user()->ledTeams()->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->title       = '';
        $this->description = '';
        $this->teamId      = null;
        $this->assignedTo  = null;
        $this->startDate   = '';
        $this->dueDate     = '';
        $this->status      = 'pending';
        $this->priority    = 'medium';
        $this->resetValidation();
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $leadTeams = auth()->user()->ledTeams()->with('project')->get();

        // Tasks visible to this lead — filtered
        $tasks = Task::with(['assignee', 'team', 'project'])
            ->whereIn('team_id', $leadTeams->pluck('id'))
            ->when($this->filterTeamId, fn ($q) => $q->where('team_id', $this->filterTeamId))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByRaw("FIELD(status, 'in_progress', 'pending', 'done')")
            ->orderBy('due_date')
            ->get();

        // Members for the team selected in the form
        $membersForForm = $this->teamId
            ? $this->ownedTeam($this->teamId)->members()->orderBy('name')->get()
            : collect();

        return view('livewire.lead.lead-task-manager',
            compact('leadTeams', 'tasks', 'membersForForm'));
    }
}
