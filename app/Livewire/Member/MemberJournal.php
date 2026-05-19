<?php

namespace App\Livewire\Member;

use App\Models\JournalLog;
use App\Models\Task;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Logs and Journal')]
class MemberJournal extends Component
{
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
        if ($this->logDate === '') {
            $this->logDate = now()->toDateString();
        }
    }

    public function addTimerMinutes(int $seconds): void
    {
        $this->minutes += max(1, (int) ceil($seconds / 60));
        $this->normalizeDuration();
    }

    public function saveTimerSession(int $seconds): void
    {
        $validated = $this->validate([
            'logDate' => ['required', 'date'],
            'selectedTaskId' => [
                'nullable',
                Rule::exists('tasks', 'id')->where('assigned_to', auth()->id()),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $totalMinutes = max(1, (int) ceil($seconds / 60));

        JournalLog::create([
            'user_id' => auth()->id(),
            'task_id' => $validated['selectedTaskId'] !== '' ? (int) $validated['selectedTaskId'] : null,
            'log_date' => $validated['logDate'],
            'minutes' => $totalMinutes,
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->reset(['selectedTaskId', 'hours', 'minutes', 'notes']);
        $this->flash = 'Timer session added to your journal.';
    }

    public function save(): void
    {
        $this->normalizeDuration();

        $validated = $this->validate([
            'logDate' => ['required', 'date'],
            'selectedTaskId' => [
                'nullable',
                Rule::exists('tasks', 'id')->where('assigned_to', auth()->id()),
            ],
            'hours' => ['required', 'integer', 'min:0', 'max:24'],
            'minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $totalMinutes = ($validated['hours'] * 60) + $validated['minutes'];

        if ($totalMinutes < 1) {
            $this->addError('minutes', 'Add at least one minute.');
            return;
        }

        JournalLog::create([
            'user_id' => auth()->id(),
            'task_id' => $validated['selectedTaskId'] !== '' ? (int) $validated['selectedTaskId'] : null,
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

    public function render()
    {
        $userId = auth()->id();

        $tasks = Task::with('project')
            ->where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
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
