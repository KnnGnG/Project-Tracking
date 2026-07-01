<?php

namespace App\Livewire\Member;

use App\Models\Team;
use App\Models\TeamMemberEvaluation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Evaluations')]
class MemberEvaluations extends Component
{
    #[Url(as: 'team')]
    public int $filterTeam = 0;

    public function mount(): void
    {
        $this->filterTeam = request()->has('team')
            ? request()->integer('team')
            : (int) session('active_team_id', 0);
        $this->normalizeAccessibleTeamFilter();
    }

    public function updatedFilterTeam(): void
    {
        $this->filterTeam = max(0, (int) $this->filterTeam);
        $this->normalizeAccessibleTeamFilter();
    }

    private function normalizeAccessibleTeamFilter(): void
    {
        if ($this->filterTeam < 1) {
            return;
        }

        if (! Team::query()
            ->whereKey($this->filterTeam)
            ->whereHas('members', fn ($members) => $members->whereKey(auth()->id()))
            ->exists()) {
            $this->filterTeam = 0;
        }
    }

    public function render()
    {
        $userId = auth()->id();

        $teams = Team::query()
            ->with('project')
            ->whereHas('members', fn ($members) => $members->whereKey($userId))
            ->orderBy('name')
            ->get();

        if ($this->filterTeam > 0 && ! $teams->contains('id', $this->filterTeam)) {
            $this->filterTeam = 0;
        }

        $evaluations = TeamMemberEvaluation::with(['team.project', 'evaluator'])
            ->where('member_id', $userId)
            ->when($this->filterTeam > 0, fn ($query) => $query->where('team_id', $this->filterTeam))
            ->latest()
            ->get();

        $summary = [
            'count' => $evaluations->count(),
            'average' => $evaluations->isNotEmpty()
                ? round($evaluations->map(fn (TeamMemberEvaluation $evaluation) => $evaluation->averageScore())->avg(), 1)
                : null,
            'latest' => $evaluations->first(),
        ];

        return view('livewire.member.member-evaluations', compact('teams', 'evaluations', 'summary'));
    }
}
