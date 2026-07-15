<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'client_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    // --- Relationships ---

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class);
    }

    public function statusChangeRequests(): HasMany
    {
        return $this->hasMany(ProjectStatusChangeRequest::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)->latest();
    }

    public function effectiveStatus(?Carbon $today = null): string
    {
        $today ??= now()->startOfDay();

        return match (true) {
            $this->status === 'completed' => 'completed',
            $this->status === 'on_hold' => 'on_hold',
            $this->end_date?->copy()->startOfDay()->lt($today) => 'overdue',
            $this->start_date?->copy()->startOfDay()->gt($today) => 'upcoming',
            $this->end_date && $today->diffInDays($this->end_date->copy()->startOfDay(), false) <= 7 => 'near_due',
            $this->status === 'active' => 'active',
            default => $this->status ?: 'not_set',
        };
    }

    public function effectiveStatusLabel(?Carbon $today = null): string
    {
        return match ($status = $this->effectiveStatus($today)) {
            'on_hold' => 'On Hold',
            'near_due' => 'Near Due',
            'not_set' => 'Not Set',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
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
