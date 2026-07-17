<?php

namespace App\Livewire\Member;

use App\Models\JournalLog;
use App\Models\Task;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

    public int $progress = 1;

    public string $notes = '';

    public ?string $flash = null;

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->filterTeam = request()->has('team')
            ? request()->integer('team')
            : (int) session('active_team_id', 0);
        $this->logDate = request()->has('date')
            ? (string) request()->query('date')
            : (string) session('member_journal_log_date', $this->logDate);
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
        $this->progress = 1;
    }

    public function updatedSelectedTaskId(): void
    {
        $taskId = (int) $this->selectedTaskId;

        if ($taskId < 1 || ! $this->memberTaskExists($taskId)) {
            $this->progress = 1;
            return;
        }

        $savedProgress = TaskMemberProgress::query()
            ->where('task_id', $taskId)
            ->where('user_id', auth()->id())
            ->value('progress');

        $this->progress = min(100, max(1, (int) ($savedProgress ?: 1)));
        $this->resetValidation('progress');
    }

    public function addTimerMinutes($seconds): void
    {
        $seconds = $this->clampTimerSeconds($seconds);
        $this->minutes += (int) ceil($seconds / 60);
        $this->normalizeDuration();
    }

    public function saveTimerSession($seconds): bool
    {
        $seconds = $this->clampTimerSeconds($seconds);
        $this->normalizeLogDate();

        $validated = $this->validate([
            'logDate' => ['required', 'date', 'before_or_equal:today'],
            'selectedTaskId' => ['required', 'integer'],
            'progress' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! blank($validated['selectedTaskId'] ?? null) && ! $this->memberTaskExists((int) $validated['selectedTaskId'])) {
            $this->addError('selectedTaskId', 'Choose one of your assigned tasks.');
            return false;
        }

        $totalMinutes = (int) ceil($seconds / 60);
        $taskId = (int) $validated['selectedTaskId'];
        $teamId = $this->teamIdForLog($taskId);

        if (! $teamId || ! $this->memberCanLogToTeam($teamId)) {
            $this->addError('selectedTaskId', 'Choose a team before logging general work.');
            return false;
        }

        DB::transaction(function () use ($taskId, $teamId, $validated, $totalMinutes): void {
            JournalLog::create([
                'user_id' => auth()->id(),
                'task_id' => $taskId,
                'team_id' => $teamId,
                'log_date' => $validated['logDate'],
                'minutes' => $totalMinutes,
                'progress' => $validated['progress'],
                'notes' => $validated['notes'] ?: null,
            ]);

            $this->recomputeTaskProgressFromLogs($taskId);
        });

        $this->dispatch('journal-log-changed');
        $this->resetAfterSave($validated['progress']);
        $this->flash = 'Timer session added to your journal.';

        return true;
    }

    public function save(): void
    {
        $this->normalizeLogDate();
        $this->normalizeDuration();

        $validated = $this->validate([
            'logDate' => ['required', 'date', 'before_or_equal:today'],
            'selectedTaskId' => ['required', 'integer'],
            'hours' => ['required', 'integer', 'min:0', 'max:24'],
            'minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'progress' => ['required', 'integer', 'min:1', 'max:100'],
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

        $taskId = (int) $validated['selectedTaskId'];
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
                'progress' => $validated['progress'],
                'notes' => $validated['notes'] ?: null,
            ]);

            $this->recomputeTaskProgressFromLogs($taskId);
        });

        $this->dispatch('journal-log-changed');
        $this->resetAfterSave($validated['progress']);
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

            $this->recomputeTaskProgressFromLogs($taskId);

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

    private function resetAfterSave(int $savedProgress): void
    {
        $this->reset(['hours', 'minutes', 'notes']);

        if ($savedProgress >= 100) {
            $this->selectedTaskId = '';
            $this->progress = 1;
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
            session(['member_journal_log_date' => $this->logDate]);
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
                session(['member_journal_log_date' => $this->logDate]);
                return;
            }
        }

        $this->logDate = $date->toDateString();
        session(['member_journal_log_date' => $this->logDate]);
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

    private function recomputeTaskProgressFromLogs(?int $taskId): void
    {
        if (! $taskId) {
            return;
        }

        $logs = JournalLog::query()
            ->where('task_id', $taskId)
            ->where('user_id', auth()->id());
        $earliestLog = (clone $logs)
            ->orderBy('log_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        $latestLog = (clone $logs)
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $progress = TaskMemberProgress::firstOrCreate(
            ['task_id' => $taskId, 'user_id' => auth()->id()],
            ['status' => 'pending', 'progress' => 0]
        );

        if ($latestLog) {
            $percentage = max(1, min(100, (int) ($latestLog->progress ?? 1)));
            $progress->progress = $percentage;
            // A task the member has submitted for review should stay in review
            // when they log more work on it, unless that work completes it —
            // only an explicit status change should move it back to in_progress.
            $progress->status = match (true) {
                $percentage === 100 => 'done',
                $progress->status === 'review' => 'review',
                default => 'in_progress',
            };
            $progress->started_at = Carbon::parse($earliestLog->log_date)->startOfDay();
            $progress->completed_at = $percentage === 100
                ? ($latestLog->created_at ?? Carbon::parse($latestLog->log_date)->endOfDay())
                : null;
        } else {
            $progress->status = 'pending';
            $progress->progress = 0;
            $progress->started_at = null;
            $progress->completed_at = null;
        }

        $progress->save();

        $this->syncParentTaskStatus($taskId);
    }

    private function syncParentTaskStatus(int $taskId): void
    {
        $task = Task::with(['memberProgress', 'assignees'])->find($taskId);

        if (! $task) {
            return;
        }

        $progress = $task->activeMemberProgress();

        if ($progress->isEmpty()) {
            return;
        }

        $status = match (true) {
            $progress->every(fn ($item) => $item->status === 'done') => 'done',
            $progress->contains(fn ($item) => $item->status === 'review') => 'review',
            $progress->contains(fn ($item) => in_array($item->status, ['in_progress', 'done'], true)) => 'in_progress',
            $progress->every(fn ($item) => $item->status === 'pending') => 'pending',
            default => 'pending',
        };

        if ($task->status !== $status) {
            $task->update([
                'status' => $status,
                'completed_at' => $status === 'done' ? ($task->completed_at ?? now()) : null,
            ]);
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

        $this->attachInferredTasksToGeneralLogs($logs->concat($recentLogs), $userId);

        return view('livewire.member.member-journal', compact('tasks', 'logs', 'dailyMinutes', 'recentLogs', 'teams'));
    }

    /**
     * Older entries could be saved as general work. Display a task for those
     * logs only when the member, team, and date identify exactly one task.
     */
    private function attachInferredTasksToGeneralLogs(Collection $logs, int $userId): void
    {
        $activeProjectId = (int) session('active_project_id', 0);
        $generalLogs = $logs->filter(fn (JournalLog $log) => ! $log->task_id && $log->team_id && $log->log_date);

        if ($generalLogs->isEmpty()) {
            return;
        }

        $candidateTasks = Task::with(['project', 'team'])
            ->whereIn('team_id', $generalLogs->pluck('team_id')->unique())
            ->where(fn ($query) => $query
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->when($activeProjectId > 0, fn ($query) => $query->where('project_id', $activeProjectId))
            ->get();

        foreach ($generalLogs as $log) {
            $logDay = $log->log_date->copy()->startOfDay();
            $matches = $candidateTasks->filter(fn (Task $task) => (int) $task->team_id === (int) $log->team_id
                && (! $task->start_date || $task->start_date->copy()->startOfDay()->lte($logDay))
                && (! $task->due_date || $task->due_date->copy()->startOfDay()->gte($logDay)))
                ->values();

            if ($matches->count() === 1) {
                $log->setRelation('task', $matches->first());
            }
        }
    }
}




