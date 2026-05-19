<?php

namespace App\Livewire\Member;

use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Tasks')]
class MemberDashboard extends Component
{
    /** Active tab: pending | in_progress | exceeded | done */
    #[Url(as: 'tab')]
    public string $activeTab = 'pending';

    /** Filter by project ID (0 = all) */
    #[Url(as: 'project')]
    public int $filterProject = 0;

    /** Sort field: due_date | priority | title */
    #[Url(as: 'sort')]
    public string $sortBy = 'due_date';

    /** Sort direction */
    #[Url(as: 'dir')]
    public string $sortDir = 'asc';

    /** Currently expanded task ID (null = none) */
    public ?int $expandedTaskId = null;

    /** Flash message */
    public ?string $flash = null;

    public function setTab(string $tab): void
    {
        $this->activeTab     = $tab;
        $this->expandedTaskId = null;
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedTaskId = ($this->expandedTaskId === $id) ? null : $id;
    }

    public function setSort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $field;
            $this->sortDir = 'asc';
        }
    }

    /**
     * Universal status setter – allows full status cycling.
     * Valid transitions a member can initiate:
     *   pending     → in_progress, done
     *   in_progress → pending, done
     *   done        → in_progress, pending
     */
    public function setStatus(int $id, string $newStatus): void
    {
        $allowed = ['pending', 'in_progress', 'done'];
        if (!in_array($newStatus, $allowed, true)) {
            return;
        }

        $task = $this->ownedTask($id);
        if (!$task) {
            return;
        }

        $updates = ['status' => $newStatus];

        // Capture the real start moment: first time a member moves task to in progress.
        if ($newStatus === 'in_progress' && !$task->start_date) {
            $updates['start_date'] = now()->toDateString();
        }

        $task->update($updates);

        $this->flash = match ($newStatus) {
            'in_progress' => 'Task marked as In Progress.',
            'done'        => 'Task marked as Done. Great work!',
            'pending'     => 'Task moved back to Pending.',
            default       => 'Task updated.',
        };

        // If the task moved out of the current tab, close the detail panel
        $this->expandedTaskId = null;
    }

    private function ownedTask(int $id): ?Task
    {
        return Task::where('id', $id)
            ->where(fn ($q) => $q
                ->where('assigned_to', auth()->id())
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey(auth()->id())))
            ->first();
    }

    public function dismissFlash(): void
    {
        $this->flash = null;
    }

    public function render()
    {
        $userId = auth()->id();
        $today  = now()->toDateString();

        // ── Gather projects this member has tasks in (for filter dropdown) ──────
        $projects = Project::whereHas('tasks', function ($q) use ($userId) {
            $q->where(function ($inner) use ($userId) {
                $inner->where('assigned_to', $userId)
                    ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId));
            });
        })
            ->orderBy('name')
            ->get(['id', 'name']);

        // ── Base query ───────────────────────────────────────────────────────────
        $base = Task::with(['project', 'team'])
            ->where(fn ($q) => $q
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)));

        if ($this->filterProject > 0) {
            $base->where('project_id', $this->filterProject);
        }

        // ── Per-tab counts (badges) ──────────────────────────────────────────────
        $counts = [
            'pending' => (clone $base)
                ->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today))
                ->count(),

            'in_progress' => (clone $base)
                ->where('status', 'in_progress')
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today))
                ->count(),

            'exceeded' => (clone $base)
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('due_date', '<', $today)
                ->count(),

            'done' => (clone $base)
                ->where('status', 'done')
                ->count(),
        ];

        // ── Tasks for the active tab ─────────────────────────────────────────────
        $tabQuery = match ($this->activeTab) {
            'pending' => (clone $base)
                ->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today)),

            'in_progress' => (clone $base)
                ->where('status', 'in_progress')
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today)),

            'exceeded' => (clone $base)
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('due_date', '<', $today),

            'done' => (clone $base)
                ->where('status', 'done'),

            default => (clone $base)->whereRaw('0=1'),
        };

        // ── Apply sort ───────────────────────────────────────────────────────────
        if ($this->sortBy === 'priority') {
            // high → medium → low (custom ordering via FIELD)
            $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';
            $tabQuery->orderByRaw(
                "FIELD(priority, 'high', 'medium', 'low') " . ($dir === 'asc' ? 'ASC' : 'DESC')
            );
        } elseif ($this->sortBy === 'title') {
            $tabQuery->orderBy('title', $this->sortDir);
        } else {
            // Default: due_date (NULLs last)
            $tabQuery->orderByRaw("due_date IS NULL, due_date {$this->sortDir}");
        }

        $tasks = $tabQuery->get();

        return view('livewire.member.member-dashboard', compact('tasks', 'counts', 'projects'));
    }
}
