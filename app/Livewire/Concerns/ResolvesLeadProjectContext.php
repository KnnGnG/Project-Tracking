<?php

namespace App\Livewire\Concerns;

use App\Models\Team;
use Illuminate\Support\Collection;

trait ResolvesLeadProjectContext
{
    private function leadTeams(): Collection
    {
        $activeProjectId = (int) session('active_project_id', 0);

        return auth()->user()
            ->ledTeams()
            ->with($this->leadTeamRelations())
            ->get()
            ->filter(fn (Team $team) => $activeProjectId > 0
                ? $team->isAssignedToProject($activeProjectId)
                : $team->assignedProjects()->isNotEmpty())
            ->values();
    }

    private function activeProjectForTeam(Team $team)
    {
        $activeProjectId = (int) session('active_project_id', 0);
        $projects = $team->assignedProjects();

        return $projects->firstWhere('id', $activeProjectId) ?? $projects->first();
    }

    protected function leadTeamRelations(): array
    {
        return ['project', 'projects'];
    }
}

