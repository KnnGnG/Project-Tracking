<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Admin Dashboard')]
class AdminDashboard extends Component
{
    /** Quickly change a user's role directly from the dashboard. */
    public function approveRole(int $userId, string $role): void
    {
        abort_unless(in_array($role, ['admin', 'client', 'team_lead', 'member'], true), 422);

        User::findOrFail($userId)->update(['role' => $role]);

        session()->flash('success', 'User role updated to '.ucfirst(str_replace('_', ' ', $role)).'.');
    }

    public function render()
    {
        // ── Site-wide stats ───────────────────────────────────────────────────
        $stats = [
            'users' => User::count(),
            'projects' => Project::count(),
            'teams' => Team::count(),
            'tasks' => Task::count(),
        ];

        $taskStats = [
            'done' => Task::where('status', 'done')->count(),
            'in_progress' => Task::where('status', 'in_progress')->count(),
            'review' => Task::where('status', 'review')->count(),
            'pending' => Task::where('status', 'pending')->count(),
            'overdue' => Task::whereIn('status', ['pending', 'in_progress', 'review'])
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ];

        // ── Recent registrations ──────────────────────────────────────────────
        // Show the 10 most recent users — admin reviews and assigns correct roles
        $newUsers = User::orderByDesc('created_at')->take(10)->get();

        // ── Active projects ───────────────────────────────────────────────────
        $activeProjects = Project::with(['teams', 'tasks'])
            ->where('status', 'active')
            ->orderBy('end_date')
            ->take(5)
            ->get();

        return view('livewire.admin.admin-dashboard',
            compact('stats', 'taskStats', 'newUsers', 'activeProjects'));
    }
}
