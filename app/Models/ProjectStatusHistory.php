<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStatusHistory extends Model
{
    protected $fillable = [
        'project_id',
        'changed_by',
        'request_id',
        'from_status',
        'to_status',
        'source',
        'reason',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProjectStatusChangeRequest::class, 'request_id');
    }
}
