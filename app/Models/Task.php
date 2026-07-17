<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Task extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Task $task): void {
            $task->attachments()->chunkById(100, function ($attachments): void {
                $attachments->each->delete();
            });
        });
    }

    protected $fillable = [
        'title',
        'description',
        'project_id',
        'team_id',
        'assigned_to',
        'created_by',
        'start_date',
        'start_time',
        'due_date',
        'status',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
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

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function memberProgress(): HasMany
    {
        return $this->hasMany(TaskMemberProgress::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
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
            && $this->due_date
            && $this->due_date->toDateString() < now()->toDateString();
    }

    /**
     * Return all assignees for a task, falling back to the single `assignee`.
     */
    public function getAllAssignees(): Collection
    {
        $assignees = $this->assignees ?? collect();

        if ($assignees->isEmpty() && $this->assignee) {
            return collect([$this->assignee]);
        }

        return $assignees;
    }

    /**
     * Progress rows for members currently assigned to this task. History for a
     * member who was later unassigned is kept in `memberProgress` but must not
     * count toward the task's derived overall status.
     */
    public function activeMemberProgress(): Collection
    {
        $assigneeIds = $this->getAllAssignees()->pluck('id');

        return $this->memberProgress->whereIn('user_id', $assigneeIds);
    }
}
