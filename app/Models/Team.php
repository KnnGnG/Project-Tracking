<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Team extends Model
{
    protected $fillable = [
        'name',
        'project_id',
        'lead_id',
    ];

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_team')->withTimestamps();
    }

    public function assignedProjects(): Collection
    {
        $this->loadMissing(['project', 'projects']);

        return $this->projects
            ->concat($this->project ? [$this->project] : [])
            ->unique('id')
            ->values();
    }

    public function activeProject(?int $activeProjectId = null)
    {
        $projects = $this->assignedProjects();

        return $activeProjectId && $activeProjectId > 0
            ? ($projects->firstWhere('id', $activeProjectId) ?? $projects->first())
            : $projects->first();
    }

    public function isAssignedToProject(int $projectId): bool
    {
        return $this->assignedProjects()->contains('id', $projectId);
    }

    public function scopeAssignedToProject(Builder $query, int $projectId): Builder
    {
        return $query->where(function (Builder $projectScope) use ($projectId): void {
            $projectScope->where('teams.project_id', $projectId)
                ->orWhereHas('projects', fn (Builder $projects) => $projects->whereKey($projectId));
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->withPivot('role', 'notes')
            ->withTimestamps();
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->withPivot('role', 'notes')
            ->wherePivot('role', 'lead')
            ->withTimestamps();
    }

    public function regularMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->withPivot('role', 'notes')
            ->wherePivot('role', 'member')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // --- Computed helpers ---

    public function completionPercentage(): int
    {
        $total = $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->tasks()->where('status', 'done')->count();

        return (int) round(($done / $total) * 100);
    }
}
