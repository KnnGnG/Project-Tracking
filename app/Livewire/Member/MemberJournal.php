<?php

namespace App\Livewire\Member;

use App\Models\JournalLog;
use App\Models\Task;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Logs and Journal')]
class MemberJournal extends Component
{
    private const MIN_TIMER_SECONDS = 1;

    private const MAX_TIMER_SECONDS = 86400;

    #[Url(as: 'date')]
    public string $logDate = '';

    #[Url(as: 'team')]
    public int $filterTeam = 0;

    public string $selectedTaskId = '';

    public int $hours = 0;

    public int $minutes = 0;

    public string $notes = '';

    public ?string $flash = null;

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->filterTeam = request()->has('team')
            ? request()->integer('team')
            : (int) session('active_team_id', 0);
        $this->normalizeLogDate();
        $this->normalizeAccessibleTeamFilter();
    }

    public function updatedLogDate(): void
    {
        $this->normalizeLogDate();
    }

    public function updatedFilterTeam(): void
    {
        $this->filterTeam = max(0, (int) $this->filterTeam);
        $this->normalizeAccessibleTeamFilter();
        $this->selectedTaskId = '';
    }

    public function addTimerMinutes($seconds): void
    {
        $seconds = $this->clampTimerSeconds($seconds);
        $this->minutes += (int) ceil($seconds / 60);
        $this->normalizeDuration();
    }

    public function saveTimerSession($seconds): void
    {
        $seconds = $this->clampTimerSeconds($seconds);
        $this->normalizeLogDate();

        $validated = $this->validate([
            'logDate' => ['required', 'date'],
            'selectedTaskId' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! blank($validated['selectedTaskId'] ?? null) && ! $this->memberTaskExists((int) $validated['selectedTaskId'])) {
            $this->addError('selectedTaskId', 'Choose one of your assigned tasks.');
            return;
        }

        $totalMinutes = (int) ceil($seconds / 60);
        $taskId = ! blank($validated['selectedTaskId'] ?? null) ? (int) $validated['selectedTaskId'] : null;
        $teamId = $this->teamIdForLog($taskId);

        if (! $teamId || ! $this->memberCanLogToTeam($teamId)) {
            $this->addError('selectedTaskId', 'Choose a team before logging general work.');
            return;
        }

        DB::transaction(function () use ($taskId, $teamId, $validated, $totalMinutes): void {
            JournalLog::create([
                'user_id' => auth()->id(),
                'task_id' => $taskId,
                'team_id' => $teamId,
                'log_date' => $validated['logDate'],
                'minutes' => $totalMinutes,
                'notes' => $validated['notes'] ?: null,
            ]);

            $this->recomputeActualStartFromLogs($taskId);
        });

        $this->dispatch('journal-log-changed');
        $this->reset(['selectedTaskId', 'hours', 'minutes', 'notes']);
        $this->flash = 'Timer session added to your journal.';
    }

    public function save(): void
    {
        $this->normalizeLogDate();
        $this->normalizeDuration();

        $validated = $this->validate([
            'logDate' => ['required', 'date'],
            'selectedTaskId' => ['nullable', 'integer'],
            'hours' => ['required', 'integer', 'min:0', 'max:24'],
            'minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! blank($validated['selectedTaskId'] ?? null) && ! $this->memberTaskExists((int) $validated['selectedTaskId'])) {
            $this->addError('selectedTaskId', 'Choose one of your assigned tasks.');
            return;
        }

        $totalMinutes = ($validated['hours'] * 60) + $validated['minutes'];

        if ($totalMinutes < 1) {
            $this->addError('minutes', 'Add at least one minute.');
            return;
        }

        $taskId = ! blank($validated['selectedTaskId'] ?? null) ? (int) $validated['selectedTaskId'] : null;
        $teamId = $this->teamIdForLog($taskId);

        if (! $teamId || ! $this->memberCanLogToTeam($teamId)) {
            $this->addError('selectedTaskId', 'Choose a team before logging general work.');
            return;
        }

        DB::transaction(function () use ($taskId, $teamId, $validated, $totalMinutes): void {
            JournalLog::create([
                'user_id' => auth()->id(),
                'task_id' => $taskId,
                'team_id' => $teamId,
                'log_date' => $validated['logDate'],
                'minutes' => $totalMinutes,
                'notes' => $validated['notes'] ?: null,
            ]);

            $this->recomputeActualStartFromLogs($taskId);
        });

        $this->dispatch('journal-log-changed');
        $this->reset(['selectedTaskId', 'hours', 'minutes', 'notes']);
        $this->flash = 'Journal log added.';
    }

    public function deleteLog(int $id): void
    {
        $deleted = DB::transaction(function () use ($id): bool {
            $log = JournalLog::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $log) {
                return false;
            }

            $taskId = $log->task_id;
            $log->delete();

            $this->recomputeActualStartFromLogs($taskId);

            return true;
        });

        if ($deleted) {
            $this->dispatch('journal-log-changed');
            $this->flash = 'Journal log deleted.';
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deleteId) {
            $this->deleteLog($this->deleteId);
        }

        $this->cancelDelete();
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function dismissFlash(): void
    {
        $this->flash = null;
    }

    private function normalizeDuration(): void
    {
        $this->hours = max(0, (int) $this->hours);
        $this->minutes = max(0, (int) $this->minutes);

        if ($this->minutes >= 60) {
            $this->hours += intdiv($this->minutes, 60);
            $this->minutes %= 60;
        }
    }

    private function clampTimerSeconds($seconds): int
    {
        return min(self::MAX_TIMER_SECONDS, max(self::MIN_TIMER_SECONDS, (int) $seconds));
    }

    private function normalizeLogDate(): void
    {
        if ($this->logDate === '') {
            $this->logDate = now()->toDateString();
            return;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $this->logDate);
            $errors = Carbon::getLastErrors();

            if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new \InvalidArgumentException('Invalid date.');
            }
        } catch (\Throwable) {
            try {
                $date = Carbon::parse($this->logDate);
            } catch (\Throwable) {
                $this->logDate = now()->toDateString();
                return;
            }
        }

        $this->logDate = $date->toDateString();
    }

    private function normalizeAccessibleTeamFilter(): void
    {
        if ($this->filterTeam < 1) {
            return;
        }

        if (! $this->memberCanLogToTeam($this->filterTeam)) {
            $this->filterTeam = 0;
        }
    }

    private function memberTaskExists(int $taskId): bool
    {
        $userId = auth()->id();

        $activeProjectId = (int) session('active_project_id', 0);

        return Task::whereKey($taskId)
            ->where(fn ($q) => $q
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->when($activeProjectId > 0, fn ($q) => $q->where('project_id', $activeProjectId))
            ->when($this->filterTeam > 0, fn ($q) => $q->where('team_id', $this->filterTeam))
            ->exists();
    }

    private function recomputeActualStartFromLogs(?int $taskId): void
    {
        if (! $taskId) {
            return;
        }

        $earliestLog = JournalLog::query()
            ->where('task_id', $taskId)
            ->where('user_id', auth()->id())
            ->orderBy('log_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $progress = TaskMemberProgress::firstOrCreate(
            ['task_id' => $taskId, 'user_id' => auth()->id()],
            ['status' => 'pending', 'progress' => 0]
        );

        if ($earliestLog) {
            $progress->started_at = Carbon::parse($earliestLog->log_date)->startOfDay();

            if ($progress->status === 'pending') {
                $progress->status = 'in_progress';
                $progress->progress = max((int) $progress->progress, 50);
            }
        } else {
            $progress->started_at = null;
        }

        $progress->save();

        $this->syncParentTaskStatus($taskId);
    }

    private function syncParentTaskStatus(int $taskId): void
    {
        $task = Task::with('memberProgress')->find($taskId);

        if (! $task || $task->memberProgress->isEmpty()) {
            return;
        }

        $status = match (true) {
            $task->memberProgress->every(fn ($item) => $item->status === 'done') => 'done',
            $task->memberProgress->contains(fn ($item) => $item->status === 'review') => 'review',
            $task->memberProgress->contains(fn ($item) => in_array($item->status, ['in_progress', 'done'], true)) => 'in_progress',
            $task->memberProgress->every(fn ($item) => $item->status === 'pending') => 'pending',
            default => 'pending',
        };

        if ($task->status !== $status) {
            $task->update(['status' => $status]);
        }
    }

    private function memberCanLogToTeam(int $teamId): bool
    {
        $userId = auth()->id();

        $activeProjectId = (int) session('active_project_id', 0);

        return Team::query()
            ->whereKey($teamId)
            ->when($activeProjectId > 0, fn ($query) => $query->assignedToProject($activeProjectId))
            ->where(fn ($query) => $query
                ->whereHas('members', fn ($members) => $members->whereKey($userId))
                ->orWhereHas('tasks', fn ($tasks) => $tasks
                    ->where('assigned_to', $userId)
                    ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId))))
            ->exists();
    }

    private function teamIdForLog(?int $taskId): ?int
    {
        if ($taskId) {
            return Task::whereKey($taskId)->value('team_id');
        }

        return $this->filterTeam > 0 ? $this->filterTeam : null;
    }

    public function render()
    {
        $this->normalizeLogDate();
        $this->normalizeAccessibleTeamFilter();

        $userId = auth()->id();
        $activeProjectId = (int) session('active_project_id', 0);

        $teams = Team::query()
            ->with(['project', 'projects'])
            ->where(fn ($query) => $query
                ->whereHas('members', fn ($members) => $members
                    ->whereKey($userId)
                    ->where('team_members.role', 'member'))
                ->orWhereHas('tasks', fn ($tasks) => $tasks
                    ->where('assigned_to', $userId)
                    ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId))))
            ->orderBy('name')
            ->get()
            ->filter(fn (Team $team) => $activeProjectId > 0
                ? $team->isAssignedToProject($activeProjectId)
                : true)
            ->values();

        $teamIds = $teams->pluck('id');

        if ($this->filterTeam > 0 && ! $teams->contains('id', $this->filterTeam)) {
            $this->filterTeam = 0;
        }

        $tasks = Task::with(['project', 'team'])
            ->whereIn('team_id', $teamIds)
            ->where(fn ($q) => $q
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->when($activeProjectId > 0, fn ($q) => $q->where('project_id', $activeProjectId))
            ->when($this->filterTeam > 0, fn ($q) => $q->where('team_id', $this->filterTeam))
            ->whereIn('status', ['pending', 'in_progress', 'review'])
            ->orderBy('title')
            ->get();

        $logs = JournalLog::with(['task.project', 'task.team', 'team.project'])
            ->where('user_id', $userId)
            ->whereIn('team_id', $teamIds)
            ->whereDate('log_date', $this->logDate)
            ->when($this->filterTeam > 0, fn ($q) => $q->where('team_id', $this->filterTeam))
            ->latest()
            ->get();

        $dailyMinutes = $logs->sum('minutes');

        $recentLogs = JournalLog::with(['task.project', 'task.team', 'team.project'])
            ->where('user_id', $userId)
            ->whereIn('team_id', $teamIds)
            ->whereDate('log_date', '!=', $this->logDate)
            ->when($this->filterTeam > 0, fn ($q) => $q->where('team_id', $this->filterTeam))
            ->latest('log_date')
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.member.member-journal', compact('tasks', 'logs', 'dailyMinutes', 'recentLogs', 'teams'));
    }
}




