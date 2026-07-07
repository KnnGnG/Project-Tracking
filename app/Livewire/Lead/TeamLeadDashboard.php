<?php

namespace App\Livewire\Lead;

use App\Livewire\Concerns\ResolvesLeadProjectContext;
use App\Models\JournalLog;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Models\Task;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Team Dashboard')]
class TeamLeadDashboard extends Component
{
    use ResolvesLeadProjectContext;

    #[On('journal-log-changed')]
    public function refreshJournalLinkedData(): void
    {
        // Listener intentionally empty; Livewire rerenders after the event action.
    }

    #[Url(as: 'team')]
    public ?int $selectedTeamId = null;

    public int $month = 1;

    public int $year = 1970;

    public array $timelineKinds = ['project', 'task', 'actual'];

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

        $requestedTeamId = request()->has('team')
            ? request()->integer('team')
            : session('active_team_id');

        $leadTeams = $this->leadTeams();
        $first = $requestedTeamId
            ? $leadTeams->firstWhere('id', $requestedTeamId)
            : null;
        $first ??= $leadTeams->first();

        if ($first) {
            $this->selectedTeamId = $first->id;
        }
    }

    public function selectTeam(int $id): void
    {
        $team = $this->leadTeams()->firstWhere('id', $id);

        if (! $team) {
            return;
        }

        $this->selectedTeamId = $team->id;
        $this->refreshActiveTeamContext($team);
        $this->cancelEventForm();
        $this->closeMemberTasksModal();
    }

    private function refreshActiveTeamContext(Team $team): void
    {
        $project = $this->activeProjectForTeam($team);

        if (! $project) {
            return;
        }

        session([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
            'active_has_self_assigned_task' => $this->hasSelfAssignedTask($project->id),
        ]);
    }

    private function hasSelfAssignedTask(int $projectId): bool
    {
        return Task::query()
            ->where('project_id', $projectId)
            ->where(fn ($query) => $query
                ->where('assigned_to', auth()->id())
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey(auth()->id())))
            ->exists();
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
        if (! in_array($kind, ['project', 'task', 'actual'], true)) {
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
        $projectIds = $this->leadTeams()
            ->flatMap(fn (Team $team) => $team->assignedProjects()->pluck('id'))
            ->unique()
            ->values();

        abort_unless($projectIds->contains($event->project_id), 403);
    }

    /** Returns the Project for the currently selected team. */
    private function currentProject()
    {
        $team = $this->leadTeams()->firstWhere('id', $this->selectedTeamId) ?? abort(404);

        return $this->activeProjectForTeam($team) ?? abort(404);
    }

    /**
     * Build calendar items for the selected team's month: project events plus team task start/due dates.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function calendarItemsByDay(
        Collection $projects,
        Collection $tasks,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): Collection {
        $byDay = collect();

        $projects->each(function (Project $project) use ($byDay, $monthStart, $monthEnd): void {
            $monthEvents = $project->events()
                ->whereBetween('event_date', [$monthStart, $monthEnd])
                ->orderBy('event_date')
                ->get();

            foreach ($monthEvents as $event) {
                $day = $event->event_date->day;
                $byDay[$day] = ($byDay[$day] ?? collect())->push([
                    'kind' => 'event',
                    'title' => $project->name.': '.$event->title,
                    'type' => $event->type,
                ]);
            }
        });

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

    private function buildCalendarWeekBars(Collection $projects, Collection $tasks, array $calendarGrid): array
    {
        $ranges = $projects
            ->filter(fn (Project $project) => $project->start_date && $project->end_date)
            ->map(fn (Project $project) => [
                'kind' => 'project',
                'title' => $project->name,
                'start' => $project->start_date,
                'end' => $project->end_date,
            ])
            ->values();

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
                        return $items->take(3);
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

    private function buildTimelineGraph(
        Collection $projects,
        Collection $tasks,
        Carbon $monthStart,
        Carbon $monthEnd,
        Collection $generalLogs,
    ): array {
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
                'displayTitle' => $title,
                'dateRange' => $start->format('M d').' - '.$end->format('M d'),
                'startDay' => $visibleStart->day,
                'span' => max(1, $span),
                'left' => round(($monthStart->diffInDays($visibleStart) / $totalDays) * 100, 2),
                'width' => max(2.5, round(($span / $totalDays) * 100, 2)),
                'tooltip' => implode("\n", $tooltipLines),
                'tooltipLines' => $tooltipLines,
                'rawStart' => $start->copy(),
                'rawEnd' => $end->copy(),
            ];
        };

        $rows = $projects
            ->map(fn (Project $project) => $project->start_date && $project->end_date
                ? $makeRow('project', 'Project', $project->name, $project->start_date, $project->end_date)
                : null)
            ->filter()
            ->values();

        $tasks
            ->filter(fn ($task) => $task->start_date || $task->due_date)
            ->sortBy(fn ($task) => optional($task->start_date ?? $task->due_date)->timestamp ?? PHP_INT_MAX)
            ->each(function ($task) use ($rows, $makeRow) {
                $start = $task->start_date ?? $task->due_date;
                $end = $task->due_date ?? $task->start_date;
                $assigneeNames = $task->assignees->pluck('name');

                if ($assigneeNames->isEmpty() && $task->assignee) {
                    $assigneeNames = collect([$task->assignee->name]);
                }

                $taskRow = $makeRow('task', 'Task', $task->title, $start, $end);

                if ($taskRow) {
                    $scheduledStart = $task->start_date
                        ? $task->start_date->format('M d, Y').($task->start_time ? ' '.Carbon::parse($task->start_time)->format('h:i A') : '')
                        : 'Not set';
                    $taskRow['displayTitle'] = $task->title;
                    $taskRow['statusLabel'] = ucwords(str_replace('_', ' ', $task->status));
                    $taskRow['tooltipLines'] = [
                        'Task',
                        $task->title,
                        'Project: '.($task->project?->name ?? 'No project'),
                        'Members: '.($assigneeNames->isNotEmpty() ? $assigneeNames->join(', ') : 'Unassigned'),
                        'Status: '.$taskRow['statusLabel'],
                        'Priority: '.ucfirst($task->priority ?? 'normal'),
                        'Scheduled start: '.$scheduledStart,
                        'Due: '.($task->due_date ? $task->due_date->format('M d, Y') : 'No due date'),
                    ];
                    $taskRow['tooltip'] = implode("\n", $taskRow['tooltipLines']);
                }

                $actualSegments = collect();
                $journalLogs = $task->journalLogs ?? collect();
                $progressRows = $task->memberProgress
                    ->filter(fn ($progress) => $progress->user)
                    ->each(fn ($progress) => $progress->hasProgressRecord = true)
                    ->values();
                $progressUserIds = $progressRows->pluck('user.id')->filter()->all();

                $fallbackUsers = $task->assignees;

                if ($fallbackUsers->isEmpty() && $task->assignee) {
                    $fallbackUsers = collect([$task->assignee]);
                }

                $fallbackUsers
                    ->reject(fn ($user) => in_array($user->id, $progressUserIds, true))
                    ->each(function ($user) use ($progressRows, $task, &$progressUserIds) {
                        $progressRows->push((object) [
                            'user' => $user,
                            'started_at' => null,
                            'completed_at' => null,
                            'status' => $task->status,
                            'updated_at' => $task->updated_at,
                            'hasProgressRecord' => false,
                        ]);
                        $progressUserIds[] = $user->id;
                    });

                $journalLogs
                    ->filter(fn ($log) => $log->user && ! in_array($log->user_id, $progressUserIds, true))
                    ->unique('user_id')
                    ->each(function ($log) use ($progressRows, $task, &$progressUserIds) {
                        $progressRows->push((object) [
                            'user' => $log->user,
                            'started_at' => null,
                            'completed_at' => null,
                            'status' => $task->status,
                            'updated_at' => $task->updated_at,
                            'hasProgressRecord' => false,
                        ]);
                        $progressUserIds[] = $log->user_id;
                    });

                $progressRows->each(function ($progress) use ($actualSegments, $makeRow, $task, $journalLogs) {
                    $userLogs = $journalLogs
                        ->where('user_id', $progress->user->id)
                        ->sortBy('log_date');
                    $startCandidates = collect([
                        optional($userLogs->first())->log_date ? [
                            'timestamp' => Carbon::parse(optional($userLogs->first())->log_date)->startOfDay(),
                            'hasTime' => false,
                        ] : null,
                        $progress->started_at ? [
                            'timestamp' => Carbon::parse($progress->started_at),
                            'hasTime' => true,
                        ] : null,
                    ])->filter(fn ($candidate) => $candidate && $candidate['timestamp']);

                    if ($startCandidates->isEmpty()) {
                        return;
                    }

                    $actualStartCandidate = $startCandidates
                        ->sortBy(fn (array $candidate) => $candidate['timestamp']->timestamp)
                        ->first();
                    $actualStartTimestamp = $actualStartCandidate['timestamp'];
                    $memberStart = $actualStartTimestamp->copy();
                    $endCandidates = collect([
                        $progress->completed_at,
                        optional($userLogs->last())->log_date,
                        $memberStart,
                    ])->filter()->map(fn ($date) => Carbon::parse($date));
                    $memberEnd = $endCandidates->sortByDesc(fn (Carbon $date) => $date->timestamp)->first();
                    $dueLabel = $task->due_date ? $task->due_date->format('M d') : 'No due date';
                    $startedLabel = $memberStart->format('M d');
                    $endLabel = $memberEnd->format('M d');
                    $scheduledStartLabel = $task->start_date
                        ? $task->start_date->format('M d, Y').($task->start_time ? ' '.Carbon::parse($task->start_time)->format('h:i A') : '')
                        : 'Not scheduled';
                    $actualStartedLabel = $actualStartTimestamp->format('M d, Y')
                        .($actualStartCandidate['hasTime'] ? ' '.$actualStartTimestamp->format('h:i A') : '');
                    $loggedMinutes = $userLogs->sum('minutes');
                    $scheduledStartDay = $task->start_date?->copy()->startOfDay();
                    $memberStartDay = $memberStart->copy()->startOfDay();
                    $dueDay = $task->due_date?->copy()->startOfDay();
                    $timing = match (true) {
                        $scheduledStartDay && $memberStartDay->lt($scheduledStartDay) => 'Started ahead of schedule',
                        $scheduledStartDay && $memberStartDay->isSameDay($scheduledStartDay) => 'Started on schedule',
                        $scheduledStartDay && $memberStartDay->gt($scheduledStartDay) => 'Started after schedule',
                        $task->due_date && $progress->completed_at && $progress->completed_at->gt($task->due_date->copy()->endOfDay()) => 'Completed late',
                        $task->due_date && $progress->completed_at && $progress->completed_at->lte($task->due_date->copy()->endOfDay()) => 'Completed on time',
                        $progress->status === 'done' => 'Done',
                        $task->due_date && ! $progress->completed_at && now()->startOfDay()->gt($task->due_date) => 'Overdue',
                        $dueDay && $memberStartDay->lt($dueDay) => 'Started before due date',
                        $dueDay && $memberStartDay->isSameDay($dueDay) => 'Started on due date',
                        $dueDay && $memberStartDay->gt($dueDay) => 'Started late',
                        default => 'Started',
                    };

                    $actualStartRow = $makeRow(
                        'actual',
                        'Start to End',
                        $progress->user->name.' - '.$startedLabel.' to '.$endLabel.' / Due '.$dueLabel.' / '.$timing,
                        $memberStart,
                        $memberEnd,
                    );

                    if ($actualStartRow) {
                        $actualStartRow['displayTitle'] = $progress->user->name.' - '.$timing;
                        // expose the precise started timestamp for the UI hover
                        $actualStartRow['startedAt'] = $actualStartTimestamp->format($actualStartCandidate['hasTime'] ? 'M d, Y h:i A' : 'M d, Y');
                        $actualStartRow['memberName'] = $progress->user->name;
                        $actualStartRow['loggedMinutes'] = $loggedMinutes;
                        $actualStartRow['tooltipLines'] = [
                            $progress->user->name,
                            'Project: '.($task->project?->name ?? 'No project'),
                            'Task: '.$task->title,
                            'Scheduled start: '.$scheduledStartLabel,
                            'Actual started: '.$actualStartedLabel,
                            'End: '.$memberEnd->format('M d, Y'),
                            'Logged: '.intdiv($loggedMinutes, 60).'h '.($loggedMinutes % 60).'m',
                            'Due: '.($task->due_date ? $task->due_date->format('M d, Y') : 'No due date'),
                            $timing,
                        ];
                        $actualStartRow['tooltip'] = implode("\n", $actualStartRow['tooltipLines']);

                        $actualSegments->push($actualStartRow);
                    }
                });

                if ($taskRow) {
                    $actualSegments = $actualSegments
                        ->sortBy(fn (array $segment) => [
                            $segment['rawStart']?->timestamp ?? PHP_INT_MAX,
                            $segment['memberName'] ?? $segment['title'] ?? '',
                        ])
                        ->values();
                    $taskRow['segments'] = $actualSegments->take(3);
                    $taskRow['segmentOverflowCount'] = max(0, $actualSegments->count() - 3);

                    $rows->push($taskRow);
                }
            });

        $generalLogs
            ->filter(fn (JournalLog $log) => $log->user && $log->log_date)
            ->groupBy('user_id')
            ->each(function (Collection $logs) use ($rows, $makeRow) {
                $logs = $logs->sortBy('log_date');
                $firstLog = $logs->first();
                $lastLog = $logs->last();

                if (! $firstLog || ! $lastLog) {
                    return;
                }

                $start = Carbon::parse($firstLog->log_date);
                $end = Carbon::parse($lastLog->log_date);
                $minutes = $logs->sum('minutes');
                $userName = $firstLog->user->name;

                $row = $makeRow('general', 'General Work', $userName, $start, $end);

                if (! $row) {
                    return;
                }

                $segment = $makeRow(
                    'actual',
                    'Start to End',
                    $userName.' - '.$start->format('M d').' to '.$end->format('M d').' / General work',
                    $start,
                    $end,
                );

                if (! $segment) {
                    return;
                }

                $segment['displayTitle'] = $userName.' - General work';
                $segment['tooltipLines'] = [
                    $userName,
                    'Type: General work',
                    'Start: '.$start->format('M d, Y'),
                    'End: '.$end->format('M d, Y'),
                    'Logged: '.intdiv($minutes, 60).'h '.($minutes % 60).'m',
                ];
                $segment['tooltip'] = implode("\n", $segment['tooltipLines']);

                $row['hidePrimary'] = true;
                $row['segments'] = collect([$segment]);
                $row['tooltipLines'] = $segment['tooltipLines'];
                $row['tooltip'] = $segment['tooltip'];

                $rows->push($row);
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
        $teams = $this->leadTeams();

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

        if ($this->selectedTeamId && ! $teams->contains('id', $this->selectedTeamId)) {
            $this->selectedTeamId = $teams->first()?->id;
        }

        if ($this->selectedTeamId) {
            $selectedTeam = auth()->user()->ledTeams()->with([
                'project.events',
                'projects.events',
                'members',
                'tasks.project',
                'tasks.assignee',
                'tasks.assignees',
                'tasks.memberProgress.user',
                'tasks.journalLogs.user',
            ])->find($this->selectedTeamId);

            if ($selectedTeam) {
                $project = $this->activeProjectForTeam($selectedTeam);

                if (! $project) {
                    $selectedTeam = null;
                }

                if ($project) {
                    $timelineProjects = collect([$project])->filter();
                    $tasks = $selectedTeam->tasks
                        ->where('project_id', $project->id)
                        ->values();
                    $monthStart = Carbon::create($this->year, $this->month, 1)->startOfDay();
                    $monthEnd = $monthStart->copy()->endOfMonth();
                    $generalLogs = JournalLog::with('user')
                        ->where('team_id', $selectedTeam->id)
                        ->whereNull('task_id')
                        ->whereBetween('log_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->orderBy('log_date')
                        ->get();
                    $calendarGrid = $this->buildCalendarGrid(
                        $this->calendarItemsByDay($timelineProjects, $tasks, $monthStart, $monthEnd)
                    );
                    $calendarWeekBars = $this->buildCalendarWeekBars($timelineProjects, $tasks, $calendarGrid);
                    $timelineGraph = $this->buildTimelineGraph($timelineProjects, $tasks, $monthStart, $monthEnd, $generalLogs);
                    $showTaskTimeline = in_array('task', $this->timelineKinds, true);
                    $showActualTimeline = in_array('actual', $this->timelineKinds, true);
                    $timelineGraph['rows'] = $timelineGraph['rows']
                        ->map(function ($row) use ($showActualTimeline, $showTaskTimeline) {
                            if (($row['kind'] ?? null) === 'task') {
                                $row['hidePrimary'] = ! $showTaskTimeline;
                            }

                            if (! $showActualTimeline) {
                                $row['segments'] = collect();
                                $row['segmentOverflowCount'] = 0;
                            }

                            return $row;
                        })
                        ->filter(function ($row) use ($showActualTimeline) {
                            if (($row['kind'] ?? null) === 'task') {
                                return in_array('task', $this->timelineKinds, true)
                                    || ($showActualTimeline && collect($row['segments'] ?? [])->isNotEmpty());
                            }

                            if (($row['kind'] ?? null) === 'general') {
                                return $showActualTimeline && collect($row['segments'] ?? [])->isNotEmpty();
                            }

                            return in_array($row['kind'], $this->timelineKinds, true);
                        })
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
