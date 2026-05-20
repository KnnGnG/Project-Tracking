<?php

namespace App\Livewire\Lead;

use App\Models\JournalLog;
use App\Models\Task;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Journal Review')]
class LeadJournalReview extends Component
{
    public string $logDate = '';
    public string $teamId = '';
    public string $memberId = '';
    public string $taskId = '';

    public function mount(): void
    {
        $this->logDate = now()->toDateString();
    }

    public function render()
    {
        $leadTeams = auth()->user()->ledTeams()->with(['project', 'members'])->get();
        $teamIds = $leadTeams->pluck('id');

        $members = User::whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
            ->orderBy('name')
            ->get();

        $tasks = Task::whereIn('team_id', $teamIds)
            ->orderBy('title')
            ->get();

        $logs = JournalLog::with(['user', 'task.project', 'task.team'])
            ->whereHas('task', fn ($q) => $q->whereIn('team_id', $teamIds))
            ->when($this->logDate, fn ($q) => $q->whereDate('log_date', $this->logDate))
            ->when($this->teamId !== '', fn ($q) => $q->whereHas('task', fn ($task) => $task->where('team_id', $this->teamId)))
            ->when($this->memberId !== '', fn ($q) => $q->where('user_id', $this->memberId))
            ->when($this->taskId !== '', fn ($q) => $q->where('task_id', $this->taskId))
            ->latest('log_date')
            ->latest()
            ->get();

        $totalMinutes = $logs->sum('minutes');

        return view('livewire.lead.lead-journal-review', compact('leadTeams', 'members', 'tasks', 'logs', 'totalMinutes'));
    }
}
