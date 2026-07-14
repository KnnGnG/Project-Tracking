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

        $newTaskNotifications = $this->newTaskNotifications($request);
        $projectTaskOverview = $this->projectTaskOverview($request, $newTaskNotifications);

        return view('projects.index', compact('projects', 'newTaskNotifications', 'projectTaskOverview'));
    }

    public function openTaskNotification(Request $request, InAppNotification $notification): RedirectResponse
    {
        abort_unless(
            (int) $notification->user_id === (int) $request->user()->id
                && $notification->type === 'task_assigned',
            404
        );

        $taskId = (int) data_get($notification->data, 'task_id');
        $taskIsAccessible = Task::query()
            ->whereKey($taskId)
            ->where(fn ($query) => $query
                ->where('assigned_to', $request->user()->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($request->user()->id)))
            ->exists();

        abort_unless($taskIsAccessible, 404);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return redirect()->to($notification->url ?: route('projects.index'));
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

    private function newTaskNotifications(Request $request): Collection
    {
        $notifications = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'task_assigned')
            ->whereNull('read_at')
            ->latest()
            ->limit(25)
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
            ->take(25)
            ->values();
    }
}

