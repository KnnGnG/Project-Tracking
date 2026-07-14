<?php

namespace App\Livewire\Member;

use App\Models\Team;
use App\Models\TeamLeadEvaluation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Team Lead Evaluation')]
class MemberLeadEvaluation extends Component
{
    #[Url(as: 'team')]
    public int $selectedTeamId = 0;

    public string $periodStart = '';
    public string $periodEnd = '';
    public int $leadershipScore = 3;
    public int $communicationScore = 3;
    public int $supportScore = 3;
    public int $organizationScore = 3;
    public int $fairnessScore = 3;
    public string $summary = '';
    public string $strengths = '';
    public string $improvements = '';
    public ?string $flash = null;

    public function mount(): void
    {
        $requestedTeamId = request()->has('team')
            ? request()->integer('team')
            : (int) session('active_team_id', 0);

        $team = $requestedTeamId > 0
            ? $this->accessibleTeams()->firstWhere('id', $requestedTeamId)
            : null;
        $team ??= $this->accessibleTeams()->first();

        if ($team) {
            $this->selectedTeamId = (int) $team->id;
        }

        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
    }

    public function updatedSelectedTeamId(): void
    {
        $this->selectedTeamId = max(0, (int) $this->selectedTeamId);

        if ($this->selectedTeamId > 0 && ! $this->accessibleTeams()->contains('id', $this->selectedTeamId)) {
            $this->selectedTeamId = 0;
        }
    }

    public function dismissFlash(): void
    {
        $this->flash = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'selectedTeamId' => ['required', 'integer'],
            'periodStart' => ['nullable', 'date'],
            'periodEnd' => ['nullable', 'date', 'after_or_equal:periodStart'],
            'leadershipScore' => ['required', 'integer', 'min:1', 'max:5'],
            'communicationScore' => ['required', 'integer', 'min:1', 'max:5'],
            'supportScore' => ['required', 'integer', 'min:1', 'max:5'],
            'organizationScore' => ['required', 'integer', 'min:1', 'max:5'],
            'fairnessScore' => ['required', 'integer', 'min:1', 'max:5'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
        ]);

        $team = $this->accessibleTeams()->firstWhere('id', (int) $data['selectedTeamId']);
        $leadId = (int) ($team?->lead_id ?: $team?->leads->first()?->id);

        if (! $team || ! $leadId) {
            $this->addError('selectedTeamId', 'Choose a team with an assigned lead.');
            return;
        }

        TeamLeadEvaluation::create([
            'team_id' => $team->id,
            'evaluator_id' => auth()->id(),
            'lead_id' => $leadId,
            'period_start' => $data['periodStart'] ?: null,
            'period_end' => $data['periodEnd'] ?: null,
            'leadership_score' => (int) $data['leadershipScore'],
            'communication_score' => (int) $data['communicationScore'],
            'support_score' => (int) $data['supportScore'],
            'organization_score' => (int) $data['organizationScore'],
            'fairness_score' => (int) $data['fairnessScore'],
            'summary' => trim($data['summary'] ?? '') ?: null,
            'strengths' => trim($data['strengths'] ?? '') ?: null,
            'improvements' => trim($data['improvements'] ?? '') ?: null,
        ]);

        $this->resetForm();
        $this->flash = 'Team lead evaluation saved.';
    }

    private function resetForm(): void
    {
        $this->leadershipScore = 3;
        $this->communicationScore = 3;
        $this->supportScore = 3;
        $this->organizationScore = 3;
        $this->fairnessScore = 3;
        $this->summary = '';
        $this->strengths = '';
        $this->improvements = '';
        $this->resetValidation();
    }

    private function accessibleTeams()
    {
        $activeProjectId = (int) session('active_project_id', 0);

        return Team::query()
            ->with(['project', 'projects', 'lead', 'leads'])
            ->whereHas('regularMembers', fn ($members) => $members->whereKey(auth()->id()))
            ->orderBy('name')
            ->get()
            ->filter(fn (Team $team) => $activeProjectId > 0
                ? $team->isAssignedToProject($activeProjectId)
                : true)
            ->values();
    }

    public function render()
    {
        $teams = $this->accessibleTeams();

        if ($this->selectedTeamId > 0 && ! $teams->contains('id', $this->selectedTeamId)) {
            $this->selectedTeamId = 0;
        }

        $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
        $selectedLead = $selectedTeam?->lead ?? $selectedTeam?->leads->first();
        $formAverage = round(collect([
            $this->leadershipScore,
            $this->communicationScore,
            $this->supportScore,
            $this->organizationScore,
            $this->fairnessScore,
        ])->avg(), 1);
        $evaluations = TeamLeadEvaluation::with(['team', 'lead'])
            ->where('evaluator_id', auth()->id())
            ->whereIn('team_id', $teams->pluck('id'))
            ->latest()
            ->get();

        return view('livewire.member.member-lead-evaluation', compact('teams', 'selectedTeam', 'selectedLead', 'formAverage', 'evaluations'));
    }
}
