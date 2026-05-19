<?php

namespace App\Livewire\Member;

use App\Models\JournalLog;
use App\Models\Task;
use Carbon\Carbon;
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

    public string $selectedTaskId = '';

    public int $hours = 0;

    public int $minutes = 0;

    public string $notes = '';

    public ?string $flash = null;

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->normalizeLogDate();
    }

    public function updatedLogDate(): void
    {
        $this->normalizeLogDate();
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

        JournalLog::create([
            'user_id' => auth()->id(),
            'task_id' => ! blank($validated['selectedTaskId'] ?? null) ? (int) $validated['selectedTaskId'] : null,
            'log_date' => $validated['logDate'],
            'minutes' => $totalMinutes,
            'notes' => $validated['notes'] ?: null,
        ]);

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

        JournalLog::create([
            'user_id' => auth()->id(),
            'task_id' => ! blank($validated['selectedTaskId'] ?? null) ? (int) $validated['selectedTaskId'] : null,
            'log_date' => $validated['logDate'],
            'minutes' => $totalMinutes,
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->reset(['selectedTaskId', 'hours', 'minutes', 'notes']);
        $this->flash = 'Journal log added.';
    }

    public function deleteLog(int $id): void
    {
        JournalLog::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        $this->flash = 'Journal log deleted.';
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

    private function memberTaskExists(int $taskId): bool
    {
        $userId = auth()->id();

        return Task::whereKey($taskId)
            ->where(fn ($q) => $q
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->exists();
    }

    public function render()
    {
        $this->normalizeLogDate();

        $userId = auth()->id();

        $tasks = Task::with('project')
            ->where(fn ($q) => $q
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->where('status', 'in_progress')
            ->orderBy('title')
            ->get();

        $logs = JournalLog::with(['task.project'])
            ->where('user_id', $userId)
            ->whereDate('log_date', $this->logDate)
            ->latest()
            ->get();

        $dailyMinutes = $logs->sum('minutes');

        $recentLogs = JournalLog::with(['task.project'])
            ->where('user_id', $userId)
            ->whereDate('log_date', '!=', $this->logDate)
            ->latest('log_date')
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.member.member-journal', compact('tasks', 'logs', 'dailyMinutes', 'recentLogs'));
    }
}
