<?php

namespace App\Livewire\Concerns;

use App\Models\Team;

trait MatchesExistingTeams
{
    protected function findMatchingTeam(
        string $name,
        ?int $leadId,
        array $memberIds,
        ?int $projectId = null,
        bool $requireMatchingContext = false,
    ): ?Team {
        $teams = Team::with('regularMembers')->get();

        if ($requireMatchingContext) {
            $teams = $teams->filter(fn (Team $team) => ($team->project_id ? (int) $team->project_id : null) === $projectId
                && ($team->lead_id ? (int) $team->lead_id : null) === $leadId);
        }

        return $teams->first(fn (Team $team) => $this->teamMatchesSignature($team, $name, $leadId, $memberIds))
            ?? $teams->first(fn (Team $team) => $this->teamNameMatches($team, $name));
    }

    private function teamMatchesSignature(Team $team, string $name, ?int $leadId, array $memberIds): bool
    {
        if (! $this->teamNameMatches($team, $name)) {
            return false;
        }

        if (($team->lead_id ? (int) $team->lead_id : null) !== $leadId) {
            return false;
        }

        $existingMembers = $team->regularMembers
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $incomingMembers = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $leadId && $id === $leadId)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $existingMembers === $incomingMembers;
    }

    private function teamNameMatches(Team $team, string $name): bool
    {
        return mb_strtolower(trim($team->name)) === mb_strtolower(trim($name));
    }
}
