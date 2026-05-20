<?php

namespace App\Livewire;

use App\Models\InAppNotification;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
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
                } elseif ($user->isTeamLead()) {
                    $q->whereIn('team_id', $user->ledTeams()->pluck('id'));
                } elseif (! $user->isAdmin()) {
                    $q->whereRaw('0 = 1');
                }
            })
            ->firstOrFail();
    }

    private function notifyComment(Task $task): void
    {
        $recipientIds = $task->assignees->pluck('id')
            ->push($task->assigned_to)
            ->push($task->team?->lead_id)
            ->filter()
            ->unique()
            ->reject(fn ($userId) => $userId === auth()->id());

        foreach ($recipientIds as $userId) {
            InAppNotification::create([
                'user_id' => $userId,
                'type' => 'task_comment',
                'title' => 'New task comment',
                'body' => $task->title,
                'url' => route('dashboard'),
                'data' => ['task_id' => $task->id],
            ]);
        }
    }
}
