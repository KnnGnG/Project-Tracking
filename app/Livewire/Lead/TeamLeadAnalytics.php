<?php

namespace App\Livewire\Lead;

use App\Models\Project;
use App\Models\Task;
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
    }

    public function render()
    {
        $teams = auth()->user()->ledTeams()->with('project')->get();

        $selectedTeam = null;
        $project = null;
        $burndown = null;
        $cfd = null;
        $punctuality = null;

        if ($this->selectedTeamId) {
            $selectedTeam = auth()->user()->ledTeams()->with(['project', 'tasks'])->find($this->selectedTeamId);

            if ($selectedTeam) {
                $project = $selectedTeam->project;
                $tasks = $selectedTeam->tasks;

                $burndown = $this->buildBurndownDataset($project, $tasks);
                $cfd = $this->buildCfdDataset($project, $tasks);
                $punctuality = $this->buildPunctualitySplit($tasks);
            }
        }

        return view('livewire.lead.team-lead-analytics', compact(
            'teams',
            'selectedTeam',
            'project',
            'burndown',
            'cfd',
            'punctuality',
        ));
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
