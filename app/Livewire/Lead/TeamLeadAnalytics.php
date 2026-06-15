<?php

namespace App\Livewire\Lead;

use App\Models\Project;
use App\Models\Task;
use App\Models\JournalLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Team Analytics')]
class TeamLeadAnalytics extends Component
{
    public ?int $selectedTeamId = null;

    public int $velocityDays = 14;

    public int $velocityMemberId = 0;

    public string $velocityTaskStatus = 'all';

    public int $completionDays = 30;

    public int $completionMemberId = 0;

    public string $completionPriority = 'all';

    public function mount(): void
    {
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
        $this->velocityMemberId = 0;
        $this->completionMemberId = 0;
    }

    public function render()
    {
        $teams = auth()->user()->ledTeams()->with('project')->get();

        $selectedTeam = null;
        $project = null;
        $completedTasks = null;
        $cfd = null;
        $punctuality = null;
        $summary = null;
        $velocity = null;

        if ($this->selectedTeamId) {
            $selectedTeam = auth()->user()->ledTeams()->with(['project', 'tasks.assignees', 'members'])->find($this->selectedTeamId);

            if ($selectedTeam) {
                $project = $selectedTeam->project;
                $tasks = $selectedTeam->tasks;

                $validMemberIds = $selectedTeam->members->pluck('id');
                if ($this->completionMemberId !== 0 && ! $validMemberIds->contains($this->completionMemberId)) {
                    $this->completionMemberId = 0;
                }

                $completedTasks = $this->buildCompletedTasksDataset(
                    $tasks,
                    $this->completionDays,
                    $this->completionMemberId,
                    $this->completionPriority,
                );
                $cfd = $this->buildCfdDataset($project, $tasks);
                $punctuality = $this->buildPunctualitySplit($tasks);
                $summary = $this->buildSummary($tasks);
                if ($this->velocityMemberId !== 0 && ! $validMemberIds->contains($this->velocityMemberId)) {
                    $this->velocityMemberId = 0;
                }

                $velocityMembers = $this->velocityMemberId === 0
                    ? $selectedTeam->members
                    : $selectedTeam->members->where('id', $this->velocityMemberId)->values();

                $velocityTasks = in_array($this->velocityTaskStatus, ['pending', 'in_progress', 'review', 'done'], true)
                    ? $tasks->where('status', $this->velocityTaskStatus)->values()
                    : $tasks;

                $velocity = $this->buildVelocityDataset(
                    $velocityMembers,
                    $velocityTasks,
                    $this->velocityDays,
                    $this->velocityTaskStatus === 'all',
                    $selectedTeam->id,
                );
            }
        }

        return view('livewire.lead.team-lead-analytics', compact(
            'teams',
            'selectedTeam',
            'project',
            'completedTasks',
            'cfd',
            'punctuality',
            'summary',
            'velocity',
        ));
    }

    /** @return array<string, mixed> */
    private function buildSummary(Collection $tasks): array
    {
        $total = $tasks->count();
        $done = $tasks->where('status', 'done')->count();
        $open = $tasks->whereIn('status', ['pending', 'in_progress', 'review'])->count();
        $review = $tasks->where('status', 'review')->count();
        $overdue = $tasks->filter(fn (Task $task) => $task->isExceededDeadline())->count();
        $dueSoon = $tasks
            ->filter(fn (Task $task) => $task->status !== 'done'
                && $task->due_date
                && ! $task->isExceededDeadline()
                && now()->startOfDay()->diffInDays($task->due_date, false) <= 3)
            ->count();
        $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        [$health, $tone, $message] = match (true) {
            $overdue > 0 => ['Needs Attention', 'red', "{$overdue} overdue task".($overdue !== 1 ? 's' : '').' need follow-up.'],
            $review > 0 => ['Review Queue', 'amber', "{$review} task".($review !== 1 ? 's are' : ' is').' waiting for review.'],
            $dueSoon > 0 => ['Due Soon', 'amber', "{$dueSoon} task".($dueSoon !== 1 ? 's are' : ' is').' due within 3 days.'],
            $progress === 100 => ['Complete', 'green', 'All tasks are complete.'],
            default => ['On Track', 'green', 'No urgent blockers found.'],
        };

        return compact('total', 'done', 'open', 'review', 'overdue', 'dueSoon', 'progress', 'health', 'tone', 'message');
    }

    /** @return array{labels:string[],values:int[],total:int}|null */
    private function buildCompletedTasksDataset(Collection $tasks, int $days, int $memberId, string $priority): ?array
    {
        $days = in_array($days, [7, 14, 30, 60], true) ? $days : 30;
        $start = now()->startOfDay()->subDays($days - 1);
        $end = now()->startOfDay();

        $completed = $tasks
            ->filter(fn (Task $task) => $task->status === 'done')
            ->filter(fn (Task $task) => Carbon::parse($task->updated_at)->betweenIncluded($start, $end->copy()->endOfDay()));

        if ($memberId !== 0) {
            $completed = $completed->filter(function (Task $task) use ($memberId) {
                $assigneeIds = $task->assignees->pluck('id');

                if ($assigneeIds->isEmpty() && $task->assigned_to) {
                    $assigneeIds = collect([$task->assigned_to]);
                }

                return $assigneeIds->contains($memberId);
            });
        }

        if (in_array($priority, ['high', 'medium', 'low'], true)) {
            $completed = $completed->where('priority', $priority);
        }

        $byDate = $completed->groupBy(fn (Task $task) => Carbon::parse($task->updated_at)->format('M j'));

        $labels = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->format('M j'))
            ->values();

        $values = $labels
            ->map(fn (string $label) => $byDate->get($label, collect())->count())
            ->values();

        return [
            'labels' => $labels->all(),
            'values' => $values->all(),
            'total' => $completed->count(),
        ];
    }

    /** @return array{labels:string[],datasets:array<int, array<string, mixed>>,includesGeneral:bool}|null */
    private function buildVelocityDataset(Collection $members, Collection $tasks, int $days, bool $includeGeneralWork, int $teamId): ?array
    {
        $taskIds = $tasks->pluck('id');

        if ($members->isEmpty() || ($taskIds->isEmpty() && ! $includeGeneralWork)) {
            return null;
        }

        $days = in_array($days, [7, 14, 30, 60], true) ? $days : 14;
        $start = now()->startOfDay()->subDays($days - 1);
        $end = now()->startOfDay();

        $labels = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->format('M j'))
            ->values();

        $logs = JournalLog::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where(function ($query) use ($taskIds, $includeGeneralWork, $teamId) {
                if ($taskIds->isNotEmpty()) {
                    $query->whereIn('task_id', $taskIds);
                }

                if ($includeGeneralWork) {
                    $query->{$taskIds->isNotEmpty() ? 'orWhere' : 'where'}(function ($general) use ($teamId) {
                        $general->whereNull('task_id')
                            ->where('team_id', $teamId);
                    });
                }
            })
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (JournalLog $log) => $log->user_id.'|'.$log->log_date->format('M j'));

        $colors = ['#2563eb', '#059669', '#f59e0b', '#e11d48', '#7c3aed', '#0891b2', '#4b5563'];

        $datasets = $members->values()
            ->map(function ($member, int $index) use ($labels, $logs, $colors) {
                return [
                    'label' => $member->name,
                    'data' => $labels
                        ->map(fn (string $label) => round(($logs->get($member->id.'|'.$label, collect())->sum('minutes') / 60), 2))
                        ->all(),
                    'backgroundColor' => $colors[$index % count($colors)],
                    'borderRadius' => 6,
                    'maxBarThickness' => 28,
                ];
            })
            ->filter(fn (array $dataset) => collect($dataset['data'])->sum() > 0)
            ->values()
            ->all();

        if ($datasets === []) {
            return null;
        }

        return [
            'labels' => $labels->all(),
            'datasets' => $datasets,
            'includesGeneral' => $includeGeneralWork,
        ];
    }

    /** @return array{labels:string[],actual:float[],ideal:float[],days_remaining:int[],meta:array}|null */
    private function buildBurndownDataset(?Project $project, Collection $tasks): ?array
    {
        if ($tasks->isEmpty()) {
            return null;
        }

        $today = now()->startOfDay();
        $firstTaskDay = Carbon::parse($tasks->min('created_at'))->startOfDay();
        $projStart = $project?->start_date?->copy()->startOfDay() ?? $firstTaskDay;
        $projEnd = $project?->end_date?->copy()->startOfDay() ?? $today->copy()->addDays(14);

        $start = $firstTaskDay->min($projStart)->startOfDay();
        $end = $today
            ->copy()
            ->max($projEnd)
            ->max(Carbon::parse($tasks->max('updated_at'))->startOfDay());

        if ($start->diffInDays($end) > 150) {
            $start = $end->copy()->subDays(120);
        }

        $labels = [];
        $actual = [];
        $ideal = [];
        $daysRemaining = [];

        $r0 = $tasks->filter(fn (Task $t) => $this->taskIncompleteAtDayEnd($t, $start))->count();

        // Ideal burndown to project end — use at least 1 day span
        $spanEnd = $projEnd->max($start);
        $span = max(1, $start->diffInDays($spanEnd));

        foreach (CarbonPeriod::create($start, $end) as $dayCarbon) {
            $day = Carbon::parse($dayCarbon)->startOfDay();
            $labels[] = $day->format('M j');

            $actual[] = round(
                (float) $tasks->filter(fn (Task $t) => $this->taskIncompleteAtDayEnd($t, $day))->count(),
                3
            );

            $elapsed = max(0, min($span, $start->diffInDays($day)));
            $ideal[] = round(max(0.0, $r0 * (1 - $elapsed / $span)), 3);

            $deadline = $project?->end_date?->copy()->startOfDay() ?? $today;
            if ($day->gt($deadline)) {
                $daysRemaining[] = 0;
            } else {
                $daysRemaining[] = (int) $day->diffInDays($deadline);
            }
        }

        return [
            'labels' => $labels,
            'actual' => $actual,
            'ideal' => $ideal,
            'days_remaining' => $daysRemaining,
            'meta' => [
                'opening_remainder' => $r0,
                'project_end' => $projEnd->toDateString(),
                'window_start' => $start->toDateString(),
            ],
        ];
    }

    private function taskIncompleteAtDayEnd(Task $task, Carbon $day): bool
    {
        $eod = $day->copy()->endOfDay();
        if ($task->created_at->gt($eod)) {
            return false;
        }

        if ($task->status === 'done') {
            return $task->updated_at->gt($eod);
        }

        return true;
    }

    /** @return array{labels:string[],to_do:int[],in_progress:int[],review:int[],done:int[]}|null */
    private function buildCfdDataset(?Project $project, Collection $tasks): ?array
    {
        if ($tasks->isEmpty()) {
            return null;
        }

        $today = now()->startOfDay();
        $firstDay = Carbon::parse($tasks->min('created_at'))->startOfDay();
        $windowStart = $project?->start_date?->copy()->startOfDay() ?? $firstDay;
        $start = $firstDay->min($windowStart)->startOfDay();
        $end = $today->copy()->startOfDay();

        if ($start->diffInDays($end) > 120) {
            $start = $end->copy()->subDays(120);
        }

        $labels = [];
        $todo = [];
        $inProgress = [];
        $review = [];
        $done = [];

        foreach (CarbonPeriod::create($start, $end) as $dayCarbon) {
            $day = Carbon::parse($dayCarbon)->startOfDay();
            $labels[] = $day->format('M j');

            $cTodo = $cIp = $cRev = $cDone = 0;

            foreach ($tasks as $task) {
                $stage = $this->cfdStageAtDayEnd($task, $day);
                if ($stage === 'todo') {
                    $cTodo++;
                } elseif ($stage === 'in_progress') {
                    $cIp++;
                } elseif ($stage === 'review') {
                    $cRev++;
                } elseif ($stage === 'done') {
                    $cDone++;
                }
            }

            $todo[] = $cTodo;
            $inProgress[] = $cIp;
            $review[] = $cRev;
            $done[] = $cDone;
        }

        return [
            'labels' => $labels,
            'to_do' => $todo,
            'in_progress' => $inProgress,
            'review' => $review,
            'done' => $done,
        ];
    }

    /**
     * Approximate CFD band per task-day using created/start/review timestamps and completion (updated_at).
     *
     * @return 'outside'|'todo'|'in_progress'|'review'|'done'
     */
    private function cfdStageAtDayEnd(Task $task, Carbon $day): string
    {
        $eod = $day->copy()->endOfDay();
        if ($task->created_at->gt($eod)) {
            return 'outside';
        }

        if ($task->status === 'done') {
            $doneDay = Carbon::parse($task->updated_at)->startOfDay();
            if ($doneDay->lte($day)) {
                return 'done';
            }

            // Not yet marked done at this snapshot — fall through using non-done logic
            return $this->cfdWorkflowStageForOpenTask($task, $day);
        }

        return $this->cfdWorkflowStageForOpenTask($task, $day);
    }

    /** @return 'todo'|'in_progress'|'review'|'done' */
    private function cfdWorkflowStageForOpenTask(Task $task, Carbon $day): string
    {
        // Review overrides if we can infer membership on this day (open review only)
        if ($task->status === 'review') {
            $reviewSince = Carbon::parse($task->updated_at)->startOfDay();

            return $reviewSince->lte($day) ? 'review' : $this->cfdNonReviewStage($task, $day);
        }

        return $this->cfdNonReviewStage($task, $day);
    }

    /** @return 'todo'|'in_progress' */
    private function cfdNonReviewStage(Task $task, Carbon $day): string
    {
        // Path after done-day check in parent: behaves like undone task progressing toward completion
        $ipBegin = $task->start_date?->copy()->startOfDay();
        if ($ipBegin === null && in_array($task->status, ['in_progress', 'done'], true)) {
            $ipBegin = Carbon::parse($task->created_at)->startOfDay();
        }

        if ($ipBegin === null || $day->lt($ipBegin)) {
            return 'todo';
        }

        return 'in_progress';
    }

    /** @return array{on_time:int,overdue:int,labels:string[]}|null */
    private function buildPunctualitySplit(Collection $tasks): ?array
    {
        $today = Carbon::today();
        $onTime = 0;
        $overdueProblem = 0;

        foreach ($tasks as $task) {
            if (is_null($task->due_date)) {
                // Cannot compare completion to a deadline; treat as neutral (not late vs a missing date).
                $onTime++;

                continue;
            }

            if ($task->status === 'done') {
                $closed = Carbon::parse($task->updated_at)->toDateString();
                if ($closed <= $task->due_date->toDateString()) {
                    $onTime++;
                } else {
                    $overdueProblem++;
                }

                continue;
            }

            if ($task->due_date->lt($today)) {
                $overdueProblem++;
            } else {
                $onTime++;
            }
        }

        if (($onTime + $overdueProblem) === 0) {
            return ['on_time' => 0, 'overdue' => 0, 'labels' => ['Early / on-time', 'Overdue']];
        }

        return [
            'on_time' => $onTime,
            'overdue' => $overdueProblem,
            'labels' => ['Early / on-time', 'Overdue'],
        ];
    }
}
