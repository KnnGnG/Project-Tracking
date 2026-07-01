<?php

namespace App\Livewire;

use App\Models\InAppNotification;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TaskDiscussion extends Component
{
    public int $taskId;

    public string $comment = '';

    public function addComment(): void
    {
        $task = $this->authorizedTask();
        $data = $this->validate(['comment' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($task, $data) {
            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'body' => $data['comment'],
            ]);

            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'type' => 'comment',
                'description' => auth()->user()->name . ' commented on the task.',
            ]);

            $this->notifyComment($task);
        });
        $this->comment = '';
    }

    public function render()
    {
        $task = $this->authorizedTask();

        $comments = $task->comments()
            ->with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->reverse();

        $activities = $task->activities()
            ->with('user')
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.task-discussion', compact('comments', 'activities'));
    }

    private function authorizedTask(): Task
    {
        $user = auth()->user();

        return Task::with(['assignees', 'team'])
            ->whereKey($this->taskId)
            ->where(function ($q) use ($user) {
                if ($user->isMember()) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($user->id));
                }

                if ($user->isTeamLead()) {
                    $q->orWhereIn('team_id', $user->ledTeams()->pluck('id'));
                }

                if (! $user->isAdmin() && ! $user->isMember() && ! $user->isTeamLead()) {
                    $q->whereRaw('0 = 1');
                }
            })
            ->firstOrFail();
    }

    private function taskNotificationUrl(Task $task, int $userId): string
    {
        $user = User::find($userId);

        if ($user && $task->team_id && $user->ledTeams()->whereKey($task->team_id)->exists()) {
            return route('lead.tasks', ['team' => $task->team_id]);
        }

        if ($user && (
            $task->assigned_to === $user->id
            || $task->assignees()->whereKey($user->id)->exists()
        )) {
            return route('member.dashboard', array_filter([
                'team' => $task->team_id,
                'project' => $task->project_id,
                'task' => $task->id,
            ]));
        }

        return route('dashboard');
    }

    private function notifyComment(Task $task): void
    {
        $leadIds = $task->team
            ? $task->team->leads()->pluck('users.id')->push($task->team->lead_id)
            : collect();

        $recipientIds = $task->assignees->pluck('id')
            ->push($task->assigned_to)
            ->merge($leadIds)
            ->filter()
            ->unique()
            ->reject(fn ($userId) => $userId === auth()->id());

        foreach ($recipientIds as $userId) {
            InAppNotification::create([
                'user_id' => $userId,
                'type' => 'task_comment',
                'title' => 'New task comment',
                'body' => $task->title,
                'url' => $this->taskNotificationUrl($task, $userId),
                'data' => ['task_id' => $task->id, 'team_id' => $task->team_id, 'project_id' => $task->project_id],
            ]);
        }
    }
}
