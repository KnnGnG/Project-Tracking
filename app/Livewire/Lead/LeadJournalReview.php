<?php

namespace App\Livewire\Lead;

use App\Models\JournalLog;
use App\Models\Task;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Journal Review')]
class LeadJournalReview extends Component
{
    #[On('journal-log-changed')]
    public function refreshJournalLinkedData(): void
    {
        // Listener intentionally empty; Livewire rerenders after the event action.
    }

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

    private function normalizeFilters($teamIds): void
    {
        $activeProjectId = (int) session('active_project_id', 0);

        if ($this->teamId !== '' && ! $teamIds->contains((int) $this->teamId)) {
            $this->teamId = '';
        }

        if ($this->memberId !== '' && ! User::query()
            ->whereKey((int) $this->memberId)
            ->whereHas('teams', fn ($teams) => $teams->whereIn('teams.id', $teamIds))
            ->exists()) {
            $this->memberId = '';
        }

        if ($this->taskId !== '' && ! Task::query()
            ->whereKey((int) $this->taskId)
            ->whereIn('team_id', $teamIds)
            ->when($activeProjectId > 0, fn ($query) => $query->where('project_id', $activeProjectId))
            ->exists()) {
            $this->taskId = '';
        }
    }

    public function render()
    {
        $leadTeams = auth()->user()->ledTeams()->with(['project', 'projects'])->get()
            ->filter(fn ($team) => $team->assignedProjects()->isNotEmpty())
            ->values();
        $teamIds = $leadTeams->pluck('id');
        $this->normalizeFilters($teamIds);

        $members = User::whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
            ->orderBy('name')
            ->get();

        $tasks = Task::whereIn('team_id', $teamIds)
            ->when($activeProjectId > 0, fn ($q) => $q->where('project_id', $activeProjectId))
            ->orderBy('title')
            ->get();

        $query = JournalLog::with(['user', 'task.project', 'task.team', 'team.project', 'team.projects'])
            ->where(function ($q) use ($teamIds, $activeProjectId) {
                $q->where(function ($general) use ($teamIds) {
                    $general->whereIn('team_id', $teamIds)
                        ->whereNull('task_id');
                })->orWhereHas('task', function ($taskQuery) use ($teamIds, $activeProjectId) {
                    $taskQuery->whereIn('team_id', $teamIds)
                        ->when($activeProjectId > 0, fn ($projectQuery) => $projectQuery->where('project_id', $activeProjectId));
                });
            })
            ->when($this->logDate, fn ($q) => $q->whereDate('log_date', $this->logDate))
            ->when($this->teamId !== '', function ($q) {
                $q->where(function ($teamQuery) {
                    $teamQuery->where('team_id', $this->teamId)
                        ->orWhereHas('task', fn ($taskQuery) => $taskQuery->where('team_id', $this->teamId));
                });
            })
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



