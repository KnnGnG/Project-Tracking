<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'project_id',
        'team_id',
        'assigned_to',
        'created_by',
        'start_date',
        'due_date',
        'status',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalLogs(): HasMany
    {
        return $this->hasMany(JournalLog::class);
    }

    // --- Scopes ---

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeReview(Builder $query): Builder
    {
        return $query->where('status', 'review');
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', 'done');
    }

    public function scopeExceededDeadline(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'in_progress', 'review'])
            ->where('due_date', '<', now()->toDateString());
    }

    // --- Computed helpers ---

    public function isExceededDeadline(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'review'], true)
            && $this->due_date->isPast();
    }
}
