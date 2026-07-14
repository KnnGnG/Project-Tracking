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
            ->each(function ($task) use ($rows, $makeRow, $monthStart, $monthEnd) {
                $start = $task->start_date ?? $task->due_date;
                $end = $task->due_date ?? $task->start_date;
                $assigneeNames = $task->assignees->pluck('name');

                if ($assigneeNames->isEmpty() && $task->assignee) {
                    $assigneeNames = collect([$task->assignee->name]);
                }

                $journalLogs = $task->journalLogs ?? collect();
                $taskRow = $makeRow('task', 'Task', $task->title, $start, $end);

                if (! $taskRow) {
                    $visibleLogs = $journalLogs
                        ->filter(fn (JournalLog $log) => $log->log_date
                            && Carbon::parse($log->log_date)->betweenIncluded($monthStart, $monthEnd))
                        ->sortBy('log_date')
                        ->values();

                    if ($visibleLogs->isNotEmpty()) {
                        $taskRow = $makeRow(
                            'task',
                            'Task',
                            $task->title,
                            Carbon::parse($visibleLogs->first()->log_date)->startOfDay(),
                            Carbon::parse($visibleLogs->last()->log_date)->startOfDay(),
                        );
                    }
                }

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
                    $dueLabel = $task->due_date ? $task->due_date->format('M d') : 'No due date';
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

                    $loggedDayGroups = $this->consecutiveLogDateGroups($userLogs);

                    if ($loggedDayGroups->isEmpty() && $progress->started_at) {
                        $loggedDayGroups = collect([collect([$memberStart->copy()->startOfDay()])]);
                    }

                    $loggedDayGroups->each(function (Collection $days) use (
                        $actualSegments,
                        $makeRow,
                        $task,
                        $progress,
                        $userLogs,
                        $loggedMinutes,
                        $scheduledStartLabel,
                        $actualStartedLabel,
                        $timing,
                        $dueLabel
                    ): void {
                        $segmentStart = $days->first()->copy()->startOfDay();
                        $segmentEnd = $days->last()->copy()->startOfDay();
                        $segmentLogs = $userLogs->filter(fn (JournalLog $log) => $days->contains(fn (Carbon $day) => $day->isSameDay($log->log_date)));
                        $segmentMinutes = $segmentLogs->sum('minutes');
                        $segmentLabel = $segmentStart->isSameDay($segmentEnd)
                            ? $segmentStart->format('M d')
                            : $segmentStart->format('M d').' to '.$segmentEnd->format('M d');

                        $actualWorkRow = $makeRow(
                            'actual',
                            'Logged Work',
                            $progress->user->name.' - '.$segmentLabel.' / Due '.$dueLabel.' / '.$timing,
                            $segmentStart,
                            $segmentEnd,
                        );

                        if (! $actualWorkRow) {
                            return;
                        }

                        $actualWorkRow['displayTitle'] = $progress->user->name.' - '.$segmentLabel;
                        $actualWorkRow['startedAt'] = $actualStartedLabel;
                        $actualWorkRow['memberName'] = $progress->user->name;
                        $actualWorkRow['loggedMinutes'] = $loggedMinutes;
                        $actualWorkRow['tooltipLines'] = [
                            $progress->user->name,
                            'Project: '.($task->project?->name ?? 'No project'),
                            'Task: '.$task->title,
                            'Scheduled start: '.$scheduledStartLabel,
                            'Actual started: '.$actualStartedLabel,
                            'Logged segment: '.$segmentStart->format('M d, Y').' - '.$segmentEnd->format('M d, Y'),
                            'Segment time: '.intdiv($segmentMinutes, 60).'h '.($segmentMinutes % 60).'m',
                            'Total logged: '.intdiv($loggedMinutes, 60).'h '.($loggedMinutes % 60).'m',
                            'Due: '.($task->due_date ? $task->due_date->format('M d, Y') : 'No due date'),
                            $timing,
                        ];
                        $actualWorkRow['tooltip'] = implode("\n", $actualWorkRow['tooltipLines']);

                        $actualSegments->push($actualWorkRow);
                    });
                });

                if ($taskRow) {
                    $taskRow['activityRows'] = $this->taskMemberActivityRows($task, $journalLogs, $monthStart, $monthEnd);
                    $taskRow['segments'] = collect();
                    $taskRow['segmentOverflowCount'] = 0;

                    $rows->push($taskRow);
                }
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

    private function consecutiveLogDateGroups(Collection $logs): Collection
    {
        $days = $logs
            ->filter(fn (JournalLog $log) => $log->log_date)
            ->map(fn (JournalLog $log) => Carbon::parse($log->log_date)->startOfDay())
            ->unique(fn (Carbon $day) => $day->toDateString())
            ->sortBy(fn (Carbon $day) => $day->timestamp)
            ->values();

        $groups = collect();
        $current = collect();
        $previous = null;

        foreach ($days as $day) {
            if ($previous && ! $previous->copy()->addDay()->isSameDay($day)) {
                $groups->push($current);
                $current = collect();
            }

            $current->push($day);
            $previous = $day;
        }

        if ($current->isNotEmpty()) {
            $groups->push($current);
        }

        return $groups;
    }

    private function taskMemberActivityRows(Task $task, Collection $logs, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        $users = $task->assignees
            ->concat($task->memberProgress->pluck('user'))
            ->concat($logs->pluck('user'))
            ->filter()
            ->whenEmpty(fn (Collection $users) => $task->assignee ? $users->push($task->assignee) : $users)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return $users->map(function ($user) use ($task, $logs, $monthStart, $monthEnd) {
            $userLogs = $logs->where('user_id', $user->id)->values();
            $progress = $task->memberProgress->first(fn ($progress) => (int) $progress->user_id === (int) $user->id);
            $days = $this->taskActivityDays($task, $userLogs, $monthStart, $monthEnd, $progress, $user->name);

            return $days->isEmpty() ? null : [
                'memberId' => $user->id,
                'memberName' => $user->name,
                'days' => $days,
            ];
        })->filter()->values();
    }

    /** Build one member's green/gray elapsed-day activity line for a task. */
    private function taskActivityDays(
        Task $task,
        Collection $logs,
        Carbon $monthStart,
        Carbon $monthEnd,
        ?object $progress,
        string $memberName,
    ): Collection
    {
        $logs = $logs
            ->filter(fn (JournalLog $log) => $log->log_date)
            ->sortBy('log_date')
            ->values();

        $startedAt = collect([
            $logs->first()?->log_date ? Carbon::parse($logs->first()->log_date)->startOfDay() : null,
            $progress?->started_at ? Carbon::parse($progress->started_at)->startOfDay() : null,
            in_array($progress?->status ?? $task->status, ['in_progress', 'review', 'done'], true)
                ? $task->start_date?->copy()->startOfDay()
                : null,
        ])->filter()->sortBy(fn (Carbon $date) => $date->timestamp)->first();

        if (! $startedAt) {
            return collect();
        }

        $completedAt = $progress?->completed_at
            ? Carbon::parse($progress->completed_at)->startOfDay()
            : null;
        $latestLogAt = $logs->last()?->log_date
            ? Carbon::parse($logs->last()->log_date)->startOfDay()
            : null;
        $effectiveStatus = $progress?->status ?? $task->status;
        $endedAt = $effectiveStatus === 'done'
            ? collect([
                $completedAt,
                $latestLogAt,
            ])->filter()->sortByDesc(fn (Carbon $date) => $date->timestamp)->first()
                ?? $task->updated_at?->copy()->startOfDay()
                ?? now()->startOfDay()
            : now()->startOfDay();

        $visibleStart = $startedAt->copy()->max($monthStart);
        $visibleEnd = $endedAt->copy()->min($monthEnd)->min(now()->startOfDay());

        if ($visibleEnd->lt($visibleStart)) {
            return collect();
        }

        $logsByDate = $logs->groupBy(fn (JournalLog $log) => Carbon::parse($log->log_date)->toDateString());
        $totalMinutes = $logs->sum('minutes');
        $scheduledStart = $task->start_date
            ? $task->start_date->format('M d, Y').($task->start_time ? ' '.Carbon::parse($task->start_time)->format('h:i A') : '')
            : 'Not scheduled';
        $actualStarted = $startedAt->format('M d, Y');
        $dueDate = $task->due_date?->format('M d, Y') ?? 'No due date';
        $days = collect();

        for ($day = $visibleStart->copy(); $day->lte($visibleEnd); $day->addDay()) {
            $date = $day->toDateString();
            $dayLogs = $logsByDate->get($date, collect());
            $hasLog = $dayLogs->isNotEmpty();
            $minutes = $dayLogs->sum('minutes');
            $stateLabel = $hasLog ? 'Journal/log added' : 'No journal/log entry';
            $tooltipLines = [
                $stateLabel.' - '.$day->format('M d, Y'),
                'Project: '.($task->project?->name ?? 'No project'),
                'Task: '.$task->title,
                'Member: '.$memberName,
                'Scheduled start: '.$scheduledStart,
                'Actual started: '.$actualStarted,
                'Time for this day: '.intdiv($minutes, 60).'h '.($minutes % 60).'m',
                'Total logged: '.intdiv($totalMinutes, 60).'h '.($totalMinutes % 60).'m',
                'Due: '.$dueDate,
                'Status: '.ucwords(str_replace('_', ' ', $effectiveStatus)),
            ];

            $days->push([
                'day' => $day->day,
                'date' => $date,
                'state' => $hasLog ? 'logged' : 'no_activity',
                'tooltip' => implode("\n", $tooltipLines),
                'tooltipLines' => $tooltipLines,
            ]);
        }

        return $days;
    }

    /**
     * Older journal entries allowed an empty task. Use them on the timeline only
     * when the member and log date identify exactly one assigned task.
     */
    private function attachUnambiguousGeneralLogsToTasks(Collection $tasks, Collection $generalLogs): void
    {
        foreach ($generalLogs as $log) {
            if (! $log->log_date) {
                continue;
            }

            $logDay = Carbon::parse($log->log_date)->startOfDay();
            $matches = $tasks->filter(function (Task $task) use ($log, $logDay): bool {
                $assignedUserIds = $task->assignees->pluck('id');

                if ($assignedUserIds->isEmpty() && $task->assigned_to) {
                    $assignedUserIds = collect([(int) $task->assigned_to]);
                }

                $startsByLogDay = ! $task->start_date || $task->start_date->copy()->startOfDay()->lte($logDay);
                $endsAfterLogDay = ! $task->due_date || $task->due_date->copy()->startOfDay()->gte($logDay);

                return $assignedUserIds->contains((int) $log->user_id)
                    && $startsByLogDay
                    && $endsAfterLogDay;
            })->values();

            if ($matches->count() !== 1) {
                continue;
            }

            $task = $matches->first();
            $task->setRelation('journalLogs', $task->journalLogs->push($log));
        }
    }

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
                        ->get();
                    $this->attachUnambiguousGeneralLogsToTasks($tasks, $generalLogs);
                    $calendarGrid = $this->buildCalendarGrid(
                        $this->calendarItemsByDay($timelineProjects, $tasks, $monthStart, $monthEnd)
                    );
                    $calendarWeekBars = $this->buildCalendarWeekBars($timelineProjects, $tasks, $calendarGrid);
                    $timelineGraph = $this->buildTimelineGraph($timelineProjects, $tasks, $monthStart, $monthEnd);
                    $showTaskTimeline = in_array('task', $this->timelineKinds, true);
                    $showActualTimeline = in_array('actual', $this->timelineKinds, true);
                    $timelineGraph['rows'] = $timelineGraph['rows']
                        ->map(function ($row) use ($showActualTimeline, $showTaskTimeline) {
                            if (($row['kind'] ?? null) === 'task') {
                                $row['hidePrimary'] = ! $showTaskTimeline;
                                $row['hideActivity'] = ! $showActualTimeline;
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
                                    || ($showActualTimeline && collect($row['activityRows'] ?? [])->isNotEmpty());
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
