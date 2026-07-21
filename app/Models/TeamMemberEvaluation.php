<?php

namespace App\Models;

use App\Support\EvaluationCriteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMemberEvaluation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'evaluator_id',
        'member_id',
        'period_start',
        'period_end',
        'quality_score',
        'productivity_score',
        'teamwork_score',
        'communication_score',
        'reliability_score',
        'criteria_labels',
        'summary',
        'strengths',
        'improvements',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'quality_score' => 'integer',
            'productivity_score' => 'integer',
            'teamwork_score' => 'integer',
            'communication_score' => 'integer',
            'reliability_score' => 'integer',
            'criteria_labels' => 'array',
        ];
    }

    /** Score column labels for this evaluation, falling back to the defaults for any slot not customized. */
    public function resolvedCriteriaLabels(): array
    {
        return array_merge(EvaluationCriteria::MEMBER_DEFAULT_LABELS, $this->criteria_labels ?? []);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function averageScore(): float
    {
        return round(collect([
            $this->quality_score,
            $this->productivity_score,
            $this->teamwork_score,
            $this->communication_score,
            $this->reliability_score,
        ])->avg(), 1);
    }
}
