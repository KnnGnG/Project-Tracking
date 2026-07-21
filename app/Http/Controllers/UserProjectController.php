<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
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

        $projectTeams = $teams
            ->flatMap(fn (Team $team) => $team->assignedProjects()
                ->map(fn (Project $project) => ['project' => $project, 'team' => $team]))
            ->groupBy(fn (array $item) => $item['project']->id);

        $projects = $projectTeams->map(function (Collection $items) {
            $teams = $items->pluck('team')->unique('id')->values();
            $item = ['project' => $items->first()['project'], 'teams' => $teams];
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

        $statusOptions = $projects
            ->map(fn (array $item) => $item['project']->effectiveStatus())
            ->unique()
            ->sort()
            ->values();

        $statusFilter = $request->query('status', 'all');
        if (! $statusOptions->contains($statusFilter)) {
            $statusFilter = 'all';
        }

        if ($statusFilter !== 'all') {
            $projects = $projects
                ->filter(fn (array $item) => $item['project']->effectiveStatus() === $statusFilter)
                ->values();
        }

        $sort = $request->query('sort', 'name');
        if (! in_array($sort, ['name', 'status', 'start_date', 'end_date'], true)) {
            $sort = 'name';
        }

        $projects = match ($sort) {
            'status' => $projects->sortBy(fn (array $item) => $item['project']->effectiveStatus())->values(),
            'start_date' => $projects->sortBy(fn (array $item) => $item['project']->start_date?->timestamp ?? PHP_INT_MAX)->values(),
            'end_date' => $projects->sortBy(fn (array $item) => $item['project']->end_date?->timestamp ?? PHP_INT_MAX)->values(),
            default => $projects,
        };

        $newTaskNotifications = $this->newTaskNotifications($request);
        $projectTaskOverview = $this->projectTaskOverview($request, $newTaskNotifications);
        $backToDashboardRoute = $this->backToDashboardRoute($teams);

        return view('projects.index', compact(
            'projects',
            'newTaskNotifications',
            'projectTaskOverview',
            'statusOptions',
            'statusFilter',
            'sort',
            'backToDashboardRoute',
        ));
    }

    public function openTaskNotification(Request $request, InAppNotification $notification): RedirectResponse
    {
        abort_unless(
            (int) $notification->user_id === (int) $request->user()->id
                && $notification->type === 'task_assigned',
            404
        );

        $taskId = (int) data_get($notification->data, 'task_id');
        $task = Task::query()
            ->whereKey($taskId)
            ->where(fn ($query) => $query
                ->where('assigned_to', $request->user()->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($request->user()->id)))
            ->first();

        abort_unless($task, 404);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $destination = route('member.dashboard', [
            'team' => $task->team_id,
            'project' => $task->project_id,
            'task' => $task->id,
        ]);

        return redirect()->to($destination ?: route('projects.index'));
    }

    public function markProjectTaskNotificationsRead(Request $request, Project $project): RedirectResponse
    {
        $hasProjectAccess = $this->involvedTeams($request)
            ->assignedToProject($project->id)
            ->exists();

        abort_unless($hasProjectAccess, 404);

        $accessibleTaskIds = Task::query()
            ->where('project_id', $project->id)
            ->where(fn ($query) => $query
                ->where('assigned_to', $request->user()->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($request->user()->id)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $notificationIds = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'task_assigned')
            ->whereNull('read_at')
            ->get(['id', 'data'])
            ->filter(fn (InAppNotification $notification) => in_array(
                (int) data_get($notification->data, 'task_id'),
                $accessibleTaskIds,
                true,
            ))
            ->pluck('id');

        if ($notificationIds->isNotEmpty()) {
            InAppNotification::whereIn('id', $notificationIds)->update(['read_at' => now()]);
        }

        return redirect()
            ->route('projects.index')
            ->with('success', 'New task notifications for '.$project->name.' marked as read.');
    }

    public function dismissTaskNotification(Request $request, InAppNotification $notification): RedirectResponse
    {
        abort_unless(
            (int) $notification->user_id === (int) $request->user()->id
                && $notification->type === 'task_assigned',
            404
        );

        $taskId = (int) data_get($notification->data, 'task_id');
        $hasAssignment = Task::query()
            ->whereKey($taskId)
            ->where(fn ($query) => $query
                ->where('assigned_to', $request->user()->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($request->user()->id)))
            ->exists();

        abort_unless($hasAssignment, 404);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return redirect()->route('projects.index');
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

    public function switchContext(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'context' => ['required', 'string', 'regex:/^\d+:\d+:(lead|member)$/'],
            'return_route' => ['nullable', 'string'],
        ]);
        [$projectId, $teamId, $role] = explode(':', $data['context']);
        $projectId = (int) $projectId;
        $teamId = (int) $teamId;

        $team = $request->user()
            ->teams()
            ->withPivot('role')
            ->with(['project', 'projects'])
            ->whereKey($teamId)
            ->wherePivot('role', $role)
            ->first();

        abort_unless($team && $team->isAssignedToProject($projectId), 404);

        session([
            'active_project_id' => $projectId,
            'active_team_id' => $teamId,
            'active_project_role' => $role,
            'active_has_self_assigned_task' => $this->hasSelfAssignedTask($request, $projectId),
        ]);

        $sameContextRoutes = $role === 'lead'
            ? ['lead.dashboard', 'lead.analytics', 'lead.tasks', 'lead.journals', 'lead.evaluations']
            : ['member.dashboard', 'member.logs', 'member.evaluations', 'member.lead-evaluation'];
        $returnRoute = in_array($data['return_route'] ?? null, $sameContextRoutes, true)
            ? $data['return_route']
            : ($role === 'lead' ? 'lead.dashboard' : 'member.dashboard');
        $parameters = $role === 'lead'
            ? ['team' => $teamId]
            : ['team' => $teamId, 'project' => $projectId];

        return redirect()->route($returnRoute, $parameters);
    }

    private function involvedTeams(Request $request)
    {
        return $request->user()
            ->teams()
            ->withPivot('role')
            ->wherePivotIn('role', ['lead', 'member']);
    }


    /** Resolves the dashboard the user was last viewing before navigating to My Projects, if any. */
    private function backToDashboardRoute(Collection $teams): ?string
    {
        $activeTeamId = (int) session('active_team_id', 0);
        $activeRole = session('active_project_role');

        if ($activeTeamId < 1 || ! in_array($activeRole, ['lead', 'member'], true)) {
            return null;
        }

        if (! $teams->contains('id', $activeTeamId)) {
            return null;
        }

        return $activeRole === 'lead'
            ? route('lead.dashboard', ['team' => $activeTeamId])
            : route('member.dashboard', ['team' => $activeTeamId, 'project' => (int) session('active_project_id', 0)]);
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

    private function newTaskNotifications(Request $request): Collection
    {
        $notifications = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'task_assigned')
            ->whereNull('read_at')
            ->latest()
            ->get();

        if ($notifications->isEmpty()) {
            return collect();
        }

        $tasks = Task::query()
            ->with(['project', 'team'])
            ->whereIn('id', $notifications->pluck('data')->map(fn ($data) => (int) data_get($data, 'task_id'))->filter())
            ->where(fn ($query) => $query
                ->where('assigned_to', $request->user()->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($request->user()->id)))
            ->get()
            ->keyBy('id');

        return $notifications
            ->map(fn (InAppNotification $notification) => [
                'notification' => $notification,
                'task' => $tasks->get((int) data_get($notification->data, 'task_id')),
            ])
            ->filter(fn (array $item) => $item['task'])
            ->values();
    }

    private function projectTaskOverview(Request $request, Collection $newTaskNotifications): Collection
    {
        $userId = $request->user()->id;
        $notificationsByTask = $newTaskNotifications
            ->unique(fn (array $item) => $item['task']->id)
            ->keyBy(fn (array $item) => $item['task']->id);

        return Task::query()
            ->with([
                'project',
                'team',
                'memberProgress' => fn ($query) => $query->where('user_id', $userId),
            ])
            ->where(fn ($query) => $query
                ->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($userId)))
            ->whereIn('status', ['pending', 'in_progress', 'review'])
            ->get()
            ->map(function (Task $task) use ($notificationsByTask): array {
                $personalStatus = $task->memberProgress->first()?->status ?? $task->status;
                $isOverdue = $personalStatus !== 'done'
                    && $task->due_date
                    && $task->due_date->toDateString() < now()->toDateString();

                return [
                    'task' => $task,
                    'notification' => $notificationsByTask->get($task->id)['notification'] ?? null,
                    'status' => $isOverdue ? 'overdue' : $personalStatus,
                ];
            })
            ->filter(fn (array $item) => $item['status'] !== 'done')
            ->sortBy(fn (array $item) => [
                match ($item['status']) {
                    'overdue' => 0,
                    'review' => 1,
                    'in_progress' => 2,
                    default => 3,
                },
                $item['task']->due_date?->timestamp ?? PHP_INT_MAX,
            ])
            ->values();
    }
}

