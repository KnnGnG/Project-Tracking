<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UserProjectController extends Controller
{
    public function index(Request $request): View
    {
        $teams = $this->involvedTeams($request)
            ->with([
                'project',
                'projects',
                'members' => fn ($query) => $query->whereKey($request->user()->id),
            ])
            ->orderBy('name')
            ->get();

        $projectTeams = collect();

        $teams->each(function (Team $team) use ($projectTeams): void {
            $projects = $team->assignedProjects();

            $projects->each(function (Project $project) use ($projectTeams, $team): void {
                $projectTeams->put(
                    $project->id,
                    [
                        'project' => $project,
                        'teams' => collect($projectTeams->get($project->id, ['teams' => collect()])['teams'])->push($team),
                    ]
                );
            });
        });

        $projects = $projectTeams->map(function (array $item) {
            $teams = $item['teams']->unique('id')->values();
            $roles = $teams
                ->map(fn (Team $team) => $this->roleForTeam($team))
                ->unique()
                ->values();

            return [
                'project' => $item['project'],
                'teams' => $teams,
                'role' => $roles->contains('lead') ? 'lead' : 'member',
                'roles' => $roles,
            ];
        })->sortBy(fn (array $item) => $item['project']->name)->values();

        return view('projects.index', compact('projects'));
    }

    public function open(Request $request, Project $project): RedirectResponse
    {
        $requestedTeamId = $request->integer('team');

        $teams = $this->involvedTeams($request)
            ->with(['project', 'projects'])
            ->when($requestedTeamId, fn ($query) => $query->whereKey($requestedTeamId))
            ->orderByRaw("CASE WHEN team_members.role = 'lead' THEN 0 ELSE 1 END")
            ->orderBy('teams.name')
            ->get();

        $team = $teams->first(fn (Team $team) => $team->isAssignedToProject($project->id));

        if (! $team) {
            return redirect()
                ->route('projects.index')
                ->with('error', 'That project is not available for your account. Choose one of your assigned projects.');
        }

        $role = $team->pivot->role ?? 'member';

        session([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => $role,
            'active_has_self_assigned_task' => $this->hasSelfAssignedTask($request, $project->id),
        ]);

        return $role === 'lead'
            ? redirect()->route('lead.dashboard', ['team' => $team->id])
            : redirect()->route('member.dashboard', ['team' => $team->id, 'project' => $project->id]);
    }

    private function involvedTeams(Request $request)
    {
        return $request->user()
            ->teams()
            ->withPivot('role')
            ->wherePivotIn('role', ['lead', 'member']);
    }


    private function roleForTeam(Team $team): string
    {
        return $team->pivot?->role
            ?? $team->members->first()?->pivot?->role
            ?? 'member';
    }

    private function hasSelfAssignedTask(Request $request, int $projectId): bool
    {
        $userId = $request->user()->id;

        return Task::query()
            ->where('project_id', $projectId)
            ->where(fn ($query) => $query
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->exists();
    }
}
