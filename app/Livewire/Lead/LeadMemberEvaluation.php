<?php

namespace App\Livewire\Lead;

use App\Livewire\Concerns\ResolvesLeadProjectContext;
use App\Models\InAppNotification;
use App\Models\JournalLog;
use App\Models\Task;
use App\Models\TaskMemberProgress;
use App\Models\Team;
use App\Models\TeamLeadEvaluation;
use App\Models\TeamMemberEvaluation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Member Evaluation')]
class LeadMemberEvaluation extends Component
{
    use ResolvesLeadProjectContext;

    #[Url(as: 'team')]
    public ?int $selectedTeamId = null;

    public ?int $selectedMemberId = null;
    public ?int $editingId = null;

    public string $periodStart = '';
    public string $periodEnd = '';
    public int $qualityScore = 3;
    public int $productivityScore = 3;
    public int $teamworkScore = 3;
    public int $communicationScore = 3;
    public int $reliabilityScore = 3;
    public string $summary = '';
    public string $strengths = '';
    public string $improvements = '';
    public ?string $flash = null;

    public function mount(): void
    {
        $requestedTeamId = request()->has('team')
            ? request()->integer('team')
            : session('active_team_id');

        $teams = $this->leadTeams();
        $team = $requestedTeamId
            ? $teams->firstWhere('id', $requestedTeamId)
            : null;
        $team ??= $teams->first();

        if ($team) {
            $this->selectedTeamId = $team->id;
            $this->refreshActiveTeamContext($team);
        }

        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
    }

    public function selectTeam(int $teamId): void
    {
        $team = $this->ownedTeam($teamId);

        if (! $team) {
            return;
        }

        $this->selectedTeamId = $team->id;
        $this->refreshActiveTeamContext($team);
        $this->resetEvaluationForm(false);
    }

    public function selectMember(int $memberId): void
    {
        if (! $this->selectedTeamId || ! $this->memberBelongsToSelectedTeam($memberId)) {
            return;
        }

        $this->resetEvaluationForm(false);
        $this->selectedMemberId = $memberId;
    }

    public function editEvaluation(int $evaluationId): void
    {
        $evaluation = TeamMemberEvaluation::query()
            ->where('evaluator_id', auth()->id())
            ->whereHas('team.leads', fn ($query) => $query->whereKey(auth()->id()))
            ->findOrFail($evaluationId);

        $this->selectedTeamId = $evaluation->team_id;
        $this->selectedMemberId = $evaluation->member_id;
        $this->editingId = $evaluation->id;
        $this->periodStart = $evaluation->period_start?->toDateString() ?? '';
        $this->periodEnd = $evaluation->period_end?->toDateString() ?? '';
        $this->qualityScore = $evaluation->quality_score;
        $this->productivityScore = $evaluation->productivity_score;
        $this->teamworkScore = $evaluation->teamwork_score;
        $this->communicationScore = $evaluation->communication_score;
        $this->reliabilityScore = $evaluation->reliability_score;
        $this->summary = $evaluation->summary ?? '';
        $this->strengths = $evaluation->strengths ?? '';
        $this->improvements = $evaluation->improvements ?? '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'selectedTeamId' => ['required', 'integer', Rule::exists('teams', 'id')],
            'selectedMemberId' => ['required', 'integer', Rule::exists('users', 'id')],
            'periodStart' => ['nullable', 'date'],
            'periodEnd' => ['nullable', 'date', 'after_or_equal:periodStart'],
            'qualityScore' => ['required', 'integer', 'min:1', 'max:5'],
            'productivityScore' => ['required', 'integer', 'min:1', 'max:5'],
            'teamworkScore' => ['required', 'integer', 'min:1', 'max:5'],
            'communicationScore' => ['required', 'integer', 'min:1', 'max:5'],
            'reliabilityScore' => ['required', 'integer', 'min:1', 'max:5'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
        ]);

        $team = $this->ownedTeam((int) $data['selectedTeamId']);
        if (! $team || ! $this->memberBelongsToSelectedTeam((int) $data['selectedMemberId'])) {
            $this->addError('selectedMemberId', 'Choose a member from one of your teams.');
            return;
        }

        $payload = [
            'team_id' => (int) $data['selectedTeamId'],
            'evaluator_id' => auth()->id(),
            'member_id' => (int) $data['selectedMemberId'],
            'period_start' => $data['periodStart'] ?: null,
            'period_end' => $data['periodEnd'] ?: null,
            'quality_score' => (int) $data['qualityScore'],
            'productivity_score' => (int) $data['productivityScore'],
            'teamwork_score' => (int) $data['teamworkScore'],
            'communication_score' => (int) $data['communicationScore'],
            'reliability_score' => (int) $data['reliabilityScore'],
            'summary' => trim($data['summary'] ?? '') ?: null,
            'strengths' => trim($data['strengths'] ?? '') ?: null,
            'improvements' => trim($data['improvements'] ?? '') ?: null,
        ];

        if ($this->duplicateEvaluationExists($payload)) {
            $this->addError('periodStart', 'An active evaluation already exists for this member, team, and period. Edit the existing evaluation instead.');
            return;
        }

        try {
            if ($this->editingId) {
                $evaluation = TeamMemberEvaluation::query()
                    ->where('evaluator_id', auth()->id())
                    ->whereHas('team.leads', fn ($query) => $query->whereKey(auth()->id()))
                    ->whereKey($this->editingId)
                    ->firstOrFail();

                if (! $this->ownedTeam((int) $evaluation->team_id)) {
                    $this->addError('selectedTeamId', 'You are no longer a lead for this team.');
                    return;
                }

                $evaluation->fill($payload);
                $shouldNotify = $evaluation->isDirty($this->evaluationNotificationFields());
                $evaluation->save();

                if ($shouldNotify) {
                    $this->notifyMemberEvaluation($evaluation, true);
                }

                $this->flash = 'Evaluation updated.';
            } else {
                $evaluation = TeamMemberEvaluation::create($payload);
                $this->notifyMemberEvaluation($evaluation, false);
                $this->flash = 'Evaluation saved.';
            }
        } catch (QueryException $exception) {
            if (! $this->isDuplicateEvaluationCollision($exception)) {
                throw $exception;
            }

            $this->addError('periodStart', 'An active evaluation already exists for this member, team, and period. Edit the existing evaluation instead.');
            return;
        }

        $memberId = $this->selectedMemberId;
        $this->resetEvaluationForm(false);
        $this->selectedMemberId = $memberId;
    }

    public function deleteEvaluation(int $evaluationId): void
    {
        TeamMemberEvaluation::query()
            ->where('evaluator_id', auth()->id())
            ->whereHas('team.leads', fn ($query) => $query->whereKey(auth()->id()))
            ->findOrFail($evaluationId)
            ->delete();

        if ($this->editingId === $evaluationId) {
            $this->resetEvaluationForm(false);
        }

        $this->flash = 'Evaluation deleted.';
    }

    public function cancelEdit(): void
    {
        $memberId = $this->selectedMemberId;
        $this->resetEvaluationForm(false);
        $this->selectedMemberId = $memberId;
    }

    public function dismissFlash(): void
    {
        $this->flash = null;
    }

    private function resetEvaluationForm(bool $resetMember = true): void
    {
        if ($resetMember) {
            $this->selectedMemberId = null;
        }

        $this->editingId = null;
        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
        $this->qualityScore = 3;
        $this->productivityScore = 3;
        $this->teamworkScore = 3;
        $this->communicationScore = 3;
        $this->reliabilityScore = 3;
        $this->summary = '';
        $this->strengths = '';
        $this->improvements = '';
        $this->resetValidation();
    }

    protected function leadTeamRelations(): array
    {
        return ['project', 'projects', 'regularMembers'];
    }
    private function ownedTeam(int $teamId): ?Team
    {
        return $this->leadTeams()->firstWhere('id', $teamId);
    }

    private function memberBelongsToSelectedTeam(int $memberId): bool
    {
        if (! $this->selectedTeamId) {
            return false;
        }

        return Team::query()
            ->whereKey($this->selectedTeamId)
            ->whereHas('leads', fn ($query) => $query->whereKey(auth()->id()))
            ->whereHas('regularMembers', fn ($query) => $query->whereKey($memberId))
            ->exists();
    }

    private function refreshActiveTeamContext(Team $team): void
    {
        session([
            'active_project_id' => $this->activeProjectForTeam($team)?->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ]);
    }

    private function duplicateEvaluationExists(array $payload): bool
    {
        return TeamMemberEvaluation::query()
            ->where('team_id', $payload['team_id'])
            ->where('evaluator_id', $payload['evaluator_id'])
            ->where('member_id', $payload['member_id'])
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->when(
                $payload['period_start'],
                fn ($query) => $query->whereDate('period_start', $payload['period_start']),
                fn ($query) => $query->whereNull('period_start')
            )
            ->when(
                $payload['period_end'],
                fn ($query) => $query->whereDate('period_end', $payload['period_end']),
                fn ($query) => $query->whereNull('period_end')
            )
            ->exists();
    }

    private function isDuplicateEvaluationCollision(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'evaluations_unique_active_period')
            || (($exception->errorInfo[1] ?? null) === 1062);
    }

    private function evaluationNotificationFields(): array
    {
        return [
            'period_start',
            'period_end',
            'quality_score',
            'productivity_score',
            'teamwork_score',
            'communication_score',
            'reliability_score',
            'summary',
            'strengths',
            'improvements',
        ];
    }

    private function notifyMemberEvaluation(TeamMemberEvaluation $evaluation, bool $updated): void
    {
        InAppNotification::create([
            'user_id' => $evaluation->member_id,
            'type' => 'member_evaluation',
            'title' => $updated ? 'Evaluation updated' : 'New evaluation available',
            'body' => ($evaluation->team?->name ?? 'Your team') . ' evaluation is ready to review.',
            'url' => route('member.evaluations', ['team' => $evaluation->team_id]),
            'data' => [
                'evaluation_id' => $evaluation->id,
                'team_id' => $evaluation->team_id,
                'period_start' => $evaluation->period_start?->toDateString(),
                'period_end' => $evaluation->period_end?->toDateString(),
            ],
        ]);
    }

    private function memberMetrics(?int $memberId, ?int $teamId): array
    {
        if (! $memberId || ! $teamId) {
            return [
                'tasks' => 0,
                'done' => 0,
                'review' => 0,
                'active' => 0,
                'loggedMinutes' => 0,
            ];
        }

        $activeProjectId = (int) session('active_project_id', 0);

        $taskQuery = Task::query()
            ->where('team_id', $teamId)
            ->when($activeProjectId > 0, fn ($query) => $query->where('project_id', $activeProjectId))
            ->where(fn ($query) => $query
                ->where('assigned_to', $memberId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($memberId)));

        $tasks = (clone $taskQuery)->get(['id', 'status']);
        $taskIds = $tasks->pluck('id');
        $progressByTask = TaskMemberProgress::query()
            ->where('user_id', $memberId)
            ->whereIn('task_id', $taskIds)
            ->pluck('status', 'task_id');
        $memberStatuses = $tasks->map(fn (Task $task) => $progressByTask->get($task->id, $task->status));

        return [
            'tasks' => $taskIds->count(),
            'done' => $memberStatuses->where(fn ($status) => $status === 'done')->count(),
            'review' => $memberStatuses->where(fn ($status) => $status === 'review')->count(),
            'active' => $memberStatuses->filter(fn ($status) => in_array($status, ['pending', 'in_progress'], true))->count(),
            'loggedMinutes' => JournalLog::query()
                ->where('user_id', $memberId)
                ->where(function ($query) use ($teamId, $activeProjectId) {
                    $query->where(function ($general) use ($teamId) {
                        $general->where('team_id', $teamId)
                            ->whereNull('task_id');
                    })->orWhereHas('task', function ($task) use ($teamId, $activeProjectId) {
                        $task->where('team_id', $teamId)
                            ->when($activeProjectId > 0, fn ($projectQuery) => $projectQuery->where('project_id', $activeProjectId));
                    });
                })
                ->sum('minutes'),
        ];
    }

    public function render()
    {
        $teams = $this->leadTeams()->sortBy('name')->values();

        if ($this->selectedTeamId && ! $teams->contains('id', $this->selectedTeamId)) {
            $this->selectedTeamId = $teams->first()?->id;
            $this->selectedMemberId = null;
        }

        $selectedTeam = $this->selectedTeamId
            ? $teams->firstWhere('id', $this->selectedTeamId)
            : $teams->first();

        if (! $this->selectedTeamId && $selectedTeam) {
            $this->selectedTeamId = $selectedTeam->id;
        }

        $members = $selectedTeam?->regularMembers ?? collect();

        if ($this->selectedMemberId && ! $members->contains('id', $this->selectedMemberId)) {
            $this->selectedMemberId = null;
        }

        $selectedMember = $this->selectedMemberId
            ? $members->firstWhere('id', $this->selectedMemberId)
            : null;

        $evaluations = TeamMemberEvaluation::with(['member', 'evaluator', 'team.project', 'team.projects'])
            ->whereIn('team_id', $teams->pluck('id'))
            ->when($this->selectedTeamId, fn ($query) => $query->where('team_id', $this->selectedTeamId))
            ->latest()
            ->get();

        $leadFeedback = TeamLeadEvaluation::with(['evaluator', 'team.project', 'team.projects'])
            ->where('lead_id', auth()->id())
            ->whereIn('team_id', $teams->pluck('id'))
            ->when($this->selectedTeamId, fn ($query) => $query->where('team_id', $this->selectedTeamId))
            ->latest()
            ->get();

        $latestByMember = $evaluations
            ->groupBy('member_id')
            ->map(fn ($rows) => $rows->first());

        $metrics = $this->memberMetrics($this->selectedMemberId, $this->selectedTeamId);

        return view('livewire.lead.lead-member-evaluation', compact(
            'teams',
            'selectedTeam',
            'members',
            'selectedMember',
            'evaluations',
            'leadFeedback',
            'latestByMember',
            'metrics',
        ));
    }
}
