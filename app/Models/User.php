<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // =========================================================================
    // Role helpers
    // =========================================================================

    /** Check if the user holds a specific role. Accepts multiple roles. */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isTeamLead(): bool
    {
        return $this->role === 'team_lead' || $this->ledTeams()->exists();
    }

    public function isMember(): bool
    {
        return $this->role === 'member' || $this->memberTeams()->exists();
    }

    /**
     * Human-readable role label for display in views.
     * e.g.  "Team Lead"  instead of  "team_lead"
     */
    public function roleName(): string
    {
        return match ($this->role) {
            'admin'     => 'Admin',
            'client'    => 'Client',
            'team_lead' => 'Team Lead',
            'member'    => 'Member',
            default     => ucfirst($this->role ?? 'Unknown'),
        };
    }

    /**
     * Returns the named route of the correct dashboard for this user.
     * Useful for redirects anywhere in the app without repeating the match logic.
     */
    public function dashboardRoute(): string
    {
        if ($this->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isClient()) {
            return 'client.dashboard';
        }

        if ($this->isTeamLead()) {
            return 'lead.dashboard';
        }

        if ($this->isMember()) {
            return 'member.dashboard';
        }

        return 'login';
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function clientProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function ledTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot('role')
            ->wherePivot('role', 'lead')
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot('role')
            ->wherePivot('role', 'member')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function assignedTasksMany(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function journalLogs(): HasMany
    {
        return $this->hasMany(JournalLog::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function taskMemberProgress(): HasMany
    {
        return $this->hasMany(TaskMemberProgress::class);
    }
}
