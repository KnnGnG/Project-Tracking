<?php

namespace App\Http\Middleware;

use App\Models\Task;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('role:admin')
     *                  ->middleware('role:admin,team_lead')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();
        $allowed = collect($roles)->contains(function (string $role) use ($user, $request): bool {
            return match ($role) {
                'team_lead' => $this->hasTeamRole($request, 'lead') || $user->role === 'team_lead',
                'member' => $this->hasTeamRole($request, 'member')
                    || $this->hasAssignedTask($request)
                    || $user->role === 'member',
                default => $user->role === $role,
            };
        });

        if (! $allowed) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }

    private function hasTeamRole(Request $request, string $role): bool
    {
        $teamId = $request->integer('team') ?: session('active_team_id');

        if ($teamId) {
            return $request->user()
                ->teams()
                ->whereKey($teamId)
                ->wherePivot('role', $role)
                ->exists();
        }

        return $request->user()
            ->teams()
            ->wherePivot('role', $role)
            ->exists();
    }

    private function hasAssignedTask(Request $request): bool
    {
        $userId = $request->user()->id;
        $teamId = $request->integer('team') ?: session('active_team_id');
        $projectId = $request->integer('project') ?: session('active_project_id');

        return Task::query()
            ->where(fn ($query) => $query
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->exists();
    }
}
