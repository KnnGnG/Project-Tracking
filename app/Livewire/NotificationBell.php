<?php

namespace App\Livewire;

use App\Models\InAppNotification;
use App\Models\Task;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markRead(int $id): void
    {
        $notification = InAppNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->first();

        $notification?->markAsRead();
    }

    public function markAllRead(): void
    {
        InAppNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        $user = auth()->user();

        $notifications = InAppNotification::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $overdueTasks = $this->overdueTasks();
        $unreadNotificationsCount = InAppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
        $unreadCount = $unreadNotificationsCount + $this->overdueTasksCount();

        return view('livewire.notification-bell', compact('notifications', 'overdueTasks', 'unreadCount', 'unreadNotificationsCount'));
    }

    private function overdueTasksQuery()
    {
        $today = now()->toDateString();
        $user = auth()->user();

        $query = Task::with(['project', 'team'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('due_date', '<', $today);

        if ($user->isMember()) {
            $query->where(fn ($q) => $q
                ->where('assigned_to', $user->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($user->id)));
        } elseif ($user->isTeamLead()) {
            $query->whereIn('team_id', $user->ledTeams()->pluck('id'));
        } elseif ($user->isClient()) {
            $query->whereHas('project', fn ($project) => $project->where('client_id', $user->id));
        }

        return $query;
    }

    private function overdueTasks()
    {
        return $this->overdueTasksQuery()
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    private function overdueTasksCount(): int
    {
        return $this->overdueTasksQuery()->count();
    }
}
