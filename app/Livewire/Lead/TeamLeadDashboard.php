<?php

namespace App\Livewire\Lead;

use App\Models\Project;
use App\Models\ProjectEvent;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Team Dashboard')]
class TeamLeadDashboard extends Component
{
    public ?int $selectedTeamId = null;

    public int $month = 1;

    public int $year = 1970;

    public array $timelineKinds = ['project', 'task', 'member', 'actual'];

    // ── Event form state ──────────────────────────────────────────────────────
    public bool $showEventForm = false;

    public ?int $editingEventId = null;

    public string $eventTitle = '';

    public string $eventDescription = '';

    public string $eventDate = '';

    public string $eventType = 'update';

    public bool $confirmingDeleteEvent = false;

    public ?int $deleteEventId = null;

    public function mount(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;

        $first = auth()->user()->ledTeams()->first();
        if ($first) {
            $this->selectedTeamId = $first->id;
        }
    }

    public function selectTeam(int $id): void
    {
        if (! auth()->user()->ledTeams()->whereKey($id)->exists()) {
            return;
        }

        $this->selectedTeamId = $id;
        $this->cancelEventForm();
        $this->closeMemberTasksModal();
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

    // ── Member tasks modal ─────────────────────────────────────────────────────

    public bool $showMemberTasksModal = false;

    public ?int $modalMemberId = null;

    public function openMemberTasks(int $userId): void
    {
        if (! $this->selectedTeamId) {
            return;
        }

        $allowed = Team::query()
            ->whereKey($this->selectedTeamId)
            ->whereHas('leads', fn ($q) => $q->whereKey(auth()->id()))
            ->whereHas('members', fn ($q) => $q->whereKey($userId))
            ->exists();

        if (! $allowed) {
            return;
        }

        $this->modalMemberId = $userId;
        $this->showMemberTasksModal = true;
    }

    public function closeMemberTasksModal(): void
    {
        $this->showMemberTasksModal = false;
        $this->modalMemberId = null;
    }

    public function toggleTimelineKind(string $kind): void
    {
        if (! in_array($kind, ['project', 'task', 'member', 'actual'], true)) {
            return;
        }

        if (in_array($kind, $this->timelineKinds, true)) {
            $this->timelineKinds = array_values(array_diff($this->timelineKinds, [$kind]));
            return;
        }

        $this->timelineKinds[] = $kind;
        $this->timelineKinds = array_values(array_unique($this->timelineKinds));
    }

    // ── Event CRUD ────────────────────────────────────────────────────────────

    public function openCreateEvent(): void
    {
        $this->resetEventForm();
        $this->showEventForm = true;
        $this->editingEventId = null;
    }

    public function openEditEvent(int $id): void
    {
        $event = ProjectEvent::findOrFail($id);
        $this->authorizeEvent($event);

        $this->editingEventId = $id;
        $this->eventTitle = $event->title;
        $this->eventDescription = $event->description ?? '';
        $this->eventDate = $event->event_date->toDateString();
        $this->eventType = $event->type;
        $this->showEventForm = true;
    }

    public function saveEvent(): void
    {
        $data = $this->validateEvent();

        $project = $this->currentProject();

        $basePayload = [
            'title' => $data['eventTitle'],
            'description' => $data['eventDescription'],
            'event_date' => $data['eventDate'],
            'type' => $data['eventType'],
        ];

        if ($this->editingEventId) {
            $event = ProjectEvent::findOrFail($this->editingEventId);
            $this->authorizeEvent($event);
            $event->update($basePayload);
            session()->flash('event_success', 'Event updated.');
        } else {
            ProjectEvent::create($basePayload + [
                'project_id' => $project->id,
                'created_by' => auth()->id(),
            ]);
            session()->flash('event_success', 'Event added to timeline.');
        }

        $this->cancelEventForm();
    }

    public function deleteEvent(int $id): void
    {
        $event = ProjectEvent::find($id);

        if (! $event) {
            return;
        }

        $this->authorizeEvent($event);
        $event->delete();
        session()->flash('event_success', 'Event removed.');
    }

    public function confirmDeleteEvent(int $id): void
    {
        $event = ProjectEvent::findOrFail($id);
        $this->authorizeEvent($event);

        $this->deleteEventId = $id;
        $this->confirmingDeleteEvent = true;
    }

    public function deleteEventConfirmed(): void
    {
        if ($this->deleteEventId) {
            $this->deleteEvent($this->deleteEventId);
        }

        $this->cancelDeleteEvent();
    }

    public function cancelDeleteEvent(): void
    {
        $this->confirmingDeleteEvent = false;
        $this->deleteEventId = null;
    }

    public function cancelEventForm(): void
    {
        $this->resetEventForm();
        $this->showEventForm = false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateEvent(): array
    {
        return $this->validate([
            'eventTitle' => 'required|string|max:255',
            'eventDescription' => 'nullable|string',
            'eventDate' => 'required|date',
            'eventType' => 'required|in:milestone,update,deadline',
        ]);
    }

    private function resetEventForm(): void
    {
        $this->eventTitle = '';
        $this->eventDescription = '';
        $this->eventDate = '';
        $this->eventType = 'update';
        $this->editingEventId = null;
        $this->resetValidation();
    }

    /** Ensures the event belongs to a project managed by this team lead. */
    private function authorizeEvent(ProjectEvent $event): void
    {
        $projectIds = auth()->user()
            ->ledTeams()
            ->pluck('project_id');

        abort_unless($projectIds->contains($event->project_id), 403);
    }

    /** Returns the Project for the currently selected team. */
    private function currentProject()
    {
        return auth()->user()->ledTeams()->findOrFail($this->selectedTeamId)->project;
    }

    /**
     * Build calendar items for the selected team's month: project events plus team task start/due dates.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function calendarItemsByDay(
        Project $project,
        Collection $tasks,
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

        $tasks->each(function ($task) use ($byDay, $monthStart, $monthEnd) {
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
        });

        return $byDay->map(fn (Collection $items) => $items->values());
    }

    private function buildCalendarGrid(Collection $itemsByDay): array
    {
        $firstDay = Carbon::create($this->year, $this->month, 1);
        $daysInMonth = $firstDay->daysInMonth;
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

            if (collect($week)->filter()->isNotEmpty()) {
                $grid[] = $week;
            }
        }

        return $grid;
    }

    private function buildCalendarWeekBars(Project $project, Collection $tasks, array $calendarGrid): array
    {
        $ranges = collect([
            [
                'kind' => 'project',
                'title' => 'Project timeline',
                'start' => $project->start_date,
                'end' => $project->end_date,
            ],
        ]);

        $tasks
            ->filter(fn ($task) => $task->start_date || $task->due_date)
            ->sortBy(fn ($task) => optional($task->start_date ?? $task->due_date)->timestamp ?? PHP_INT_MAX)
            ->each(function ($task) use ($ranges) {
                $start = $task->start_date ?? $task->due_date;
                $end = $task->due_date ?? $task->start_date;

                $ranges->push([
                    'kind' => 'task',
                    'title' => $task->title,
                    'start' => $start,
                    'end' => $end->lt($start) ? $start : $end,
                ]);

                $assigneeNames = $task->assignees->pluck('name');

                if ($assigneeNames->isEmpty() && $task->assignee) {
                    $assigneeNames = collect([$task->assignee->name]);
                }

                if ($assigneeNames->isNotEmpty()) {
                    $ranges->push([
                        'kind' => 'member',
                        'title' => $assigneeNames->take(2)->join(', '),
                        'start' => $start,
                        'end' => $end->lt($start) ? $start : $end,
                    ]);
                }
            });

        return collect($calendarGrid)
            ->map(function (array $week) use ($ranges) {
                $realCells = collect($week)->filter();

                if ($realCells->isEmpty()) {
                    return [];
                }

                $weekStart = Carbon::parse($realCells->first()['date'])->startOfDay();
                $weekEnd = Carbon::parse($realCells->last()['date'])->endOfDay();

                return $ranges
                    ->filter(fn ($range) => $range['start'] && $range['end'])
                    ->filter(fn ($range) => $range['start']->lte($weekEnd) && $range['end']->gte($weekStart))
                    ->groupBy('kind')
                    ->flatMap(function (Collection $items, string $kind) {
                        return $kind === 'project' ? $items->take(1) : $items->take(3);
                    })
                    ->map(function (array $range) use ($weekStart, $weekEnd) {
                        $start = $range['start']->copy()->max($weekStart);
                        $end = $range['end']->copy()->min($weekEnd);

                        return [
                            'kind' => $range['kind'],
                            'title' => $range['title'],
                            'startColumn' => $start->dayOfWeek + 1,
                            'span' => max(1, $end->dayOfWeek - $start->dayOfWeek + 1),
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->all();
    }

    private function buildTimelineGraph(Project $project, Collection $tasks, Carbon $monthStart, Carbon $monthEnd): array
    {
        $totalDays = max(1, (int) $monthStart->diffInDays($monthEnd) + 1);

        $makeRow = function (
            string $kind,
            string $label,
            string $title,
            Carbon $start,
            Carbon $end,
        ) use ($monthStart, $monthEnd, $totalDays): ?array {
            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }

            if ($end->lt($monthStart) || $start->gt($monthEnd)) {
                return null;
            }

            $visibleStart = $start->copy()->max($monthStart);
            $visibleEnd = $end->copy()->min($monthEnd);
            $span = (int) $visibleStart->diffInDays($visibleEnd) + 1;

            $dateRange = $start->format('M d, Y').' - '.$end->format('M d, Y');
            $tooltipLines = [$label, $title, $dateRange];

            return [
                'kind' => $kind,
                'label' => $label,
                'title' => $title,
                'dateRange' => $start->format('M d').' - '.$end->format('M d'),
                'startDay' => $visibleStart->day,
                'span' => max(1, $span),
                'left' => round(($monthStart->diffInDays($visibleStart) / $totalDays) * 100, 2),
                'width' => max(2.5, round(($span / $totalDays) * 100, 2)),
                'tooltip' => implode("\n", $tooltipLines),
                'tooltipLines' => $tooltipLines,
            ];
        };

        $rows = collect([
            $makeRow('project', 'Project', $project->name, $project->start_date, $project->end_date),
        ])->filter();

        $tasks
            ->filter(fn ($task) => $task->start_date || $task->due_date)
            ->sortBy(fn ($task) => optional($task->start_date ?? $task->due_date)->timestamp ?? PHP_INT_MAX)
            ->each(function ($task) use ($rows, $makeRow) {
                $start = $task->start_date ?? $task->due_date;
                $end = $task->due_date ?? $task->start_date;

                $taskRow = $makeRow('task', 'Task', $task->title, $start, $end);

                if ($taskRow) {
                    $taskRow['statusLabel'] = ucwords(str_replace('_', ' ', $task->status));
                    $taskRow['tooltipLines'] = [
                        'Task',
                        $task->title,
                        'Status: '.$taskRow['statusLabel'],
                        'Priority: '.ucfirst($task->priority ?? 'normal'),
                        'Start: '.($task->start_date ? $task->start_date->format('M d, Y') : 'Not set'),
                        'Due: '.($task->due_date ? $task->due_date->format('M d, Y') : 'No due date'),
                    ];
                    $taskRow['tooltip'] = implode("\n", $taskRow['tooltipLines']);

                    $rows->push($taskRow);
                }

                $assigneeNames = $task->assignees->pluck('name');

                if ($assigneeNames->isEmpty() && $task->assignee) {
                    $assigneeNames = collect([$task->assignee->name]);
                }

                if ($assigneeNames->isNotEmpty()) {
                    $memberRow = $makeRow('member', 'Member', $assigneeNames->join(', '), $start, $end);

                    if ($memberRow) {
                        $rows->push($memberRow);
                    }
                }

                $progressRows = $task->memberProgress->filter(fn ($progress) => $progress->user);

                if ($progressRows->isEmpty() && $task->assignee) {
                    $progressRows = collect([(object) [
                        'user' => $task->assignee,
                        'started_at' => $task->start_date,
                        'completed_at' => null,
                        'status' => $task->status,
                        'updated_at' => $task->updated_at,
                    ]]);
                }

                $progressRows->each(function ($progress) use ($rows, $makeRow, $task) {
                    $memberStart = $progress->started_at
                        ?? ($progress->status !== 'pending' ? $progress->updated_at : null)
                        ?? $task->start_date;

                    if (! $memberStart) {
                        return;
                    }

                    $memberEnd = $task->due_date ?? $memberStart;
                    $dueLabel = $task->due_date ? $task->due_date->format('M d') : 'No due date';
                    $startedLabel = $memberStart->format('M d');
                    $timing = match (true) {
                        $task->due_date && $progress->completed_at && $progress->completed_at->gt($task->due_date->copy()->endOfDay()) => 'Completed late',
                        $task->due_date && $progress->completed_at && $progress->completed_at->lte($task->due_date->copy()->endOfDay()) => 'Completed on time',
                        $progress->status === 'done' => 'Done',
                        $task->due_date && ! $progress->completed_at && now()->startOfDay()->gt($task->due_date) => 'Overdue',
                        $task->due_date && $memberStart->lt($task->due_date) => 'Started early',
                        $task->due_date && $memberStart->isSameDay($task->due_date) => 'Started on due date',
                        $task->due_date && $memberStart->gt($task->due_date) => 'Started late',
                        default => 'Started',
                    };

                    $actualStartRow = $makeRow(
                        'actual',
                        'Actual',
                        $progress->user->name.' - Started '.$startedLabel.' / Due '.$dueLabel.' / '.$timing,
                        $memberStart,
                        $memberEnd,
                    );

                    if ($actualStartRow) {
                        // expose the precise started timestamp for the UI hover
                        $actualStartRow['startedAt'] = $progress->started_at?->format('M d, Y h:i A') ?? null;
                        $actualStartRow['tooltipLines'] = [
                            $progress->user->name,
                            'Task: '.$task->title,
                            'Started: '.($progress->started_at?->format('M d, Y h:i A') ?? $memberStart->format('M d, Y')),
                            'Due: '.($task->due_date ? $task->due_date->format('M d, Y') : 'No due date'),
                            $timing,
                        ];
                        $actualStartRow['tooltip'] = implode("\n", $actualStartRow['tooltipLines']);

                        $rows->push($actualStartRow);
                    }
                });
            });

        $today = now();
        $todayDay = $today->betweenIncluded($monthStart, $monthEnd) ? $today->day : null;

        $tickDays = collect(range(1, $monthEnd->day))
            ->map(function (int $day) use ($monthStart, $totalDays) {
                $date = $monthStart->copy()->day($day);

                return [
                    'day' => $day,
                    'weekday' => $date->format('D'),
                    'left' => round((($day - 1) / $totalDays) * 100, 2),
                    'major' => $day === 1 || $date->isMonday(),
                ];
            });

        return [
            'rows' => $rows->values(),
            'ticks' => $tickDays,
            'totalDays' => $totalDays,
            'todayDay' => $todayDay,
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $teams = auth()->user()
            ->ledTeams()
            ->with('project')
            ->get();

        $selectedTeam = null;
        $project = null;
        $stats = null;
        $tasksByPriority = collect();
        $memberTasksMap = collect();
        $memberStartActivities = collect();
        $events = collect();
        $daysRemaining = null;
        $progressPct = 0;
        $calendarGrid = [];
        $calendarWeekBars = [];
        $atRiskTasks = collect();
        $timelineGraph = [
            'rows' => collect(),
            'ticks' => collect(),
            'totalDays' => 30,
            'todayDay' => null,
        ];
        $monthLabel = Carbon::create($this->year, $this->month, 1)->format('F Y');

        if ($this->selectedTeamId) {
            $selectedTeam = auth()->user()->ledTeams()->with([
                'project.events',
                'members',
                'tasks.assignee',
                'tasks.assignees',
                'tasks.memberProgress.user',
            ])->find($this->selectedTeamId);

            if ($selectedTeam) {
                $project = $selectedTeam->project;
                $tasks = $selectedTeam->tasks;
                $monthStart = Carbon::create($this->year, $this->month, 1)->startOfDay();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $calendarGrid = $this->buildCalendarGrid(
                    $this->calendarItemsByDay($project, $tasks, $monthStart, $monthEnd)
                );
                $calendarWeekBars = $this->buildCalendarWeekBars($project, $tasks, $calendarGrid);
                $timelineGraph = $this->buildTimelineGraph($project, $tasks, $monthStart, $monthEnd);
                $timelineGraph['rows'] = $timelineGraph['rows']
                    ->filter(fn ($row) => in_array($row['kind'], $this->timelineKinds, true))
                    ->values();

                $total = $tasks->count();
                $done = $tasks->where('status', 'done')->count();
                $progressPct = $total > 0 ? (int) round(($done / $total) * 100) : 0;

                $stats = [
                    'total' => $total,
                    'done' => $done,
                    'inProgress' => $tasks->where('status', 'in_progress')->count(),
                    'review' => $tasks->where('status', 'review')->count(),
                    'pending' => $tasks->where('status', 'pending')->count(),
                    'overdue' => $tasks->filter(fn ($t) => $t->isExceededDeadline())->count(),
                    'members' => $selectedTeam->members->count(),
                ];

                $tasksByPriority = $tasks->groupBy('priority');
                $atRiskTasks = $tasks
                    ->filter(fn ($task) => $task->status !== 'done'
                        && $task->due_date
                        && ! $task->isExceededDeadline()
                        && now()->startOfDay()->diffInDays($task->due_date, false) <= 3)
                    ->sortBy('due_date')
                    ->take(5)
                    ->values();
                $memberTasksMap = collect();
                $tasks
                    ->sortBy(fn ($task) => optional($task->due_date)->timestamp ?? PHP_INT_MAX)
                    ->each(function ($task) use ($memberTasksMap) {
                        $assigneeIds = $task->assignees->pluck('id');

                        if ($assigneeIds->isEmpty() && $task->assigned_to) {
                            $assigneeIds = collect([$task->assigned_to]);
                        }

                        foreach ($assigneeIds as $assigneeId) {
                            $memberTasksMap->put(
                                $assigneeId,
                                $memberTasksMap->get($assigneeId, collect())->push($task)
                            );
                        }
                    });
                $memberStartActivities = $tasks
                    ->filter(fn ($task) => ! is_null($task->start_date))
                    ->sortByDesc('start_date')
                    ->values();

                $events = $project->events()
                    ->orderBy('event_date')
                    ->get()
                    ->map(function ($event) {
                        $event->is_past = $event->event_date->isPast();
                        $event->is_today = $event->event_date->isToday();
                        $event->days_diff = (int) now()->startOfDay()
                            ->diffInDays($event->event_date, false);

                        return $event;
                    });

                $daysRemaining = (int) now()->startOfDay()
                    ->diffInDays($project->end_date, false);
            }
        }

        $modalMember = null;
        $modalMemberTasks = collect();
        if ($this->showMemberTasksModal && $this->modalMemberId && $selectedTeam
            && $selectedTeam->members->contains('id', $this->modalMemberId)) {
            $modalMember = $selectedTeam->members->firstWhere('id', $this->modalMemberId);
            $modalMemberTasks = $memberTasksMap->get($this->modalMemberId, collect());
        }

        return view('livewire.lead.team-lead-dashboard', compact(
            'teams', 'selectedTeam', 'project', 'stats',
            'tasksByPriority', 'memberTasksMap', 'memberStartActivities', 'events', 'daysRemaining', 'progressPct',
            'modalMember', 'modalMemberTasks', 'calendarGrid', 'calendarWeekBars', 'timelineGraph', 'monthLabel', 'atRiskTasks',
        ));
    }
}
