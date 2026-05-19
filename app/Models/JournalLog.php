<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLog extends Model
{
    protected $fillable = [
        'user_id',
        'task_id',
        'log_date',
        'minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'minutes'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
