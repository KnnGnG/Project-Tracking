<?php

namespace App\Livewire\Lead;

use App\Models\JournalLog;
use App\Models\Task;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Journal Review')]
class LeadJournalReview extends Component
{
    use WithPagination;

    public string $logDate = '';
    public string $teamId = '';
    public string $memberId = '';
    public string $taskId = '';

    public function mount(): void
    {
        $this->logDate = now()->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['logDate', 'teamId', 'memberId', 'taskId'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $leadTeams = auth()->user()->ledTeams()->with('project')->get();
        $teamIds = $leadTeams->pluck('id');

        $members = User::whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
            ->orderBy('name')
            ->get();

        $tasks = Task::whereIn('team_id', $teamIds)
            ->orderBy('title')
            ->get();

        $query = JournalLog::with(['user', 'task.project', 'task.team'])
            ->whereHas('task', fn ($q) => $q->whereIn('team_id', $teamIds))
            ->when($this->logDate, fn ($q) => $q->whereDate('log_date', $this->logDate))
            ->when($this->teamId !== '', fn ($q) => $q->whereHas('task', fn ($task) => $task->where('team_id', $this->teamId)))
            ->when($this->memberId !== '', fn ($q) => $q->where('user_id', $this->memberId))
            ->when($this->taskId !== '', fn ($q) => $q->where('task_id', $this->taskId));

        $totalMinutes = (clone $query)->sum('minutes');

        $logs = $query
            ->latest('log_date')
            ->latest()
            ->paginate(100);

        return view('livewire.lead.lead-journal-review', compact('leadTeams', 'members', 'tasks', 'logs', 'totalMinutes'));
    }
}
