<?php

namespace App\Http\Middleware;

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
        $allowed = collect($roles)->contains(function (string $role) use ($user): bool {
            return match ($role) {
                'team_lead' => $user->isTeamLead(),
                'member' => $user->isMember(),
                default => $user->role === $role,
            };
        });

        if (! $allowed) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
