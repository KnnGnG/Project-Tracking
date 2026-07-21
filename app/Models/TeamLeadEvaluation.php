<?php

namespace App\Models;

use App\Support\EvaluationCriteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamLeadEvaluation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'evaluator_id',
        'lead_id',
        'period_start',
        'period_end',
        'leadership_score',
        'communication_score',
        'support_score',
        'organization_score',
        'fairness_score',
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
            'leadership_score' => 'integer',
            'communication_score' => 'integer',
            'support_score' => 'integer',
            'organization_score' => 'integer',
            'fairness_score' => 'integer',
            'criteria_labels' => 'array',
        ];
    }

    /** Score column labels for this evaluation, falling back to the defaults for any slot not customized. */
    public function resolvedCriteriaLabels(): array
    {
        return array_merge(EvaluationCriteria::LEAD_DEFAULT_LABELS, $this->criteria_labels ?? []);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function averageScore(): float
    {
        return round(collect([
            $this->leadership_score,
            $this->communication_score,
            $this->support_score,
            $this->organization_score,
            $this->fairness_score,
        ])->avg(), 1);
    }
}
