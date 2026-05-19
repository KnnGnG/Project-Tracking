<?php

namespace App\Livewire\Client;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Projects')]
class ClientDashboard extends Component
{
    /** Sidebar shows this many rows; each source query is bounded the same so merge + take matches full data. */
    private const int UPCOMING_SIDEBAR_LIMIT = 5;

    public ?int $selectedProjectId = null;

    public int $month;

    public int $year;

    public function mount(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;

        // Pre-select the first available project
        $first = $this->clientProjects()->first();
        if ($first) {
            $this->selectedProjectId = $first->id;
        }
    }

    public function selectProject(int $id): void
    {
        if (! $this->clientProjects()->whereKey($id)->exists()) {
            return;
        }

        $this->selectedProjectId = $id;
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->month = $date->month;
        $this->year = $date->year;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->month = $date->month;
        $this->year = $date->year;
    }

    public function goToToday(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
    }

    // -------------------------------------------------------------------------

    /** Projects that belong to the authenticated client. */
    private function clientProjects(): Builder
    {
        return Project::where('client_id', auth()->id())->orderBy('name');
    }

    /**
     * Load the selected project only if it belongs to this client.
     * Resets selectedProjectId when it is missing, deleted, or not owned (e.g. tampered request).
     */
    private function resolveSelectedProject(): ?Project
    {
        if ($this->selectedProjectId === null) {
            return null;
        }

        $project = $this->clientProjects()
            ->with(['tasks', 'events', 'teams'])
            ->find($this->selectedProjectId);

        if ($project) {
            return $project;
        }

        $this->selectedProjectId = $this->clientProjects()->value('id');

        if ($this->selectedProjectId === null) {
            return null;
        }

        return $this->clientProjects()
            ->with(['tasks', 'events', 'teams'])
            ->find($this->selectedProjectId);
    }

    /**
     * Build calendar items for a month: project events plus task start/due dates.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function calendarItemsByDay(
        Project $project,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): Collection {
        $byDay = collect();

        $monthEvents = $project->events()
            ->whereBetween('event_date', [$monthStart, $monthEnd])
            ->orderBy('event_date')
            ->get();

        foreach ($monthEvents as $event) {
            $day = $event->event_date->day;
            $byDay[$day] = ($byDay[$day] ?? collect())->push([
                'kind' => 'event',
                'title' => $event->title,
                'type' => $event->type,
            ]);
        }

        $monthTasks = $project->tasks()
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orWhereBetween('start_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->get();

        foreach ($monthTasks as $task) {
            $dueInMonth = $task->due_date
                && $task->due_date->gte($monthStart)
                && $task->due_date->lte($monthEnd);
            $startInMonth = $task->start_date
                && $task->start_date->gte($monthStart)
                && $task->start_date->lte($monthEnd);

            $sameDay = $dueInMonth && $startInMonth
                && $task->due_date->isSameDay($task->start_date);

            if ($dueInMonth) {
                $day = $task->due_date->day;
                $byDay[$day] = ($byDay[$day] ?? collect())->push([
                    'kind' => 'task',
                    'title' => $sameDay ? $task->title : $task->title.' (due)',
                    'variant' => 'task_due',
                    'status' => $task->status,
                ]);
            }

            if ($startInMonth && ! $sameDay) {
                $day = $task->start_date->day;
                $byDay[$day] = ($byDay[$day] ?? collect())->push([
                    'kind' => 'task',
                    'title' => $task->title.' (start)',
                    'variant' => 'task_start',
                    'status' => $task->status,
                ]);
            }
        }

        return $byDay->map(fn (Collection $items) => $items->values());
    }

    /** Build the 6×7 calendar grid for the current month/year. */
    private function buildCalendarGrid(Collection $itemsByDay): array
    {
        $firstDay = Carbon::create($this->year, $this->month, 1);
        $daysInMonth = $firstDay->daysInMonth;

        // Day-of-week of the 1st (0=Sun … 6=Sat)
        $startOffset = $firstDay->dayOfWeek;

        $grid = [];
        $day = 1;

        for ($row = 0; $row < 6; $row++) {
            $week = [];
            for ($col = 0; $col < 7; $col++) {
                $cellIndex = $row * 7 + $col;

                if ($cellIndex < $startOffset || $day > $daysInMonth) {
                    $week[] = null;
                } else {
                    $week[] = [
                        'day' => $day,
                        'date' => Carbon::create($this->year, $this->month, $day)->toDateString(),
                        'today' => now()->year === $this->year
                                    && now()->month === $this->month
                                    && now()->day === $day,
                        'items' => $itemsByDay->get($day, collect()),
                    ];
                    $day++;
                }
            }

            // Only add rows that have at least one real day
            if (collect($week)->filter()->isNotEmpty()) {
                $grid[] = $week;
            }
        }

        return $grid;
    }

    public function render()
    {
        $projects = $this->clientProjects()->withCount('tasks')->get();

        $selectedProject = $this->resolveSelectedProject();

        $itemsByDay = collect();
        $upcomingItems = collect();

        if ($selectedProject) {
            $monthStart = Carbon::create($this->year, $this->month, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $itemsByDay = $this->calendarItemsByDay($selectedProject, $monthStart, $monthEnd);

            // Upcoming: merge events + task due dates, next N by date.
            // Each source is limited to N rows (sorted ascending): the global top N cannot need
            // an (N+1)th row from either list without one of the first N from the other being earlier.
            $today = now()->startOfDay();
            $n = self::UPCOMING_SIDEBAR_LIMIT;

            $upcomingItems = $selectedProject->events()
                ->where('event_date', '>=', $today)
                ->orderBy('event_date')
                ->limit($n)
                ->get()
                ->map(fn ($e) => [
                    'kind' => 'event',
                    'date' => $e->event_date,
                    'title' => $e->title,
                    'description' => $e->description,
                    'type' => $e->type,
                ]);

            $upcomingTasks = $selectedProject->tasks()
                ->whereNotNull('due_date')
                ->where('due_date', '>=', $today)
                ->whereIn('status', ['pending', 'in_progress', 'review'])
                ->orderBy('due_date')
                ->limit($n)
                ->get()
                ->map(fn ($t) => [
                    'kind' => 'task',
                    'date' => $t->due_date,
                    'title' => $t->title,
                    'description' => $t->description,
                    'type' => 'task_due',
                    'status' => $t->status,
                ]);

            $upcomingItems = $upcomingItems->concat($upcomingTasks)
                ->sortBy('date')
                ->take($n)
                ->values();
        }

        $calendarGrid = $this->buildCalendarGrid($itemsByDay);

        // Stats for selected project
        $stats = null;
        if ($selectedProject) {
            $tasks = $selectedProject->tasks;
            $totalTasks = $tasks->count();
            $doneTasks = $tasks->where('status', 'done')->count();
            $pendingTasks = $tasks->whereIn('status', ['pending', 'in_progress', 'review'])->count();
            $overdueTasks = $tasks->filter(fn ($t) => $t->isExceededDeadline())->count();

            $stats = compact('totalTasks', 'doneTasks', 'pendingTasks', 'overdueTasks');
        }

        return view('livewire.client.client-dashboard', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'calendarGrid' => $calendarGrid,
            'upcomingItems' => $upcomingItems,
            'stats' => $stats,
            'monthLabel' => Carbon::create($this->year, $this->month, 1)->format('F Y'),
        ]);
    }
}
