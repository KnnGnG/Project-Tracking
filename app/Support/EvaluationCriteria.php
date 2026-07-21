<?php

namespace App\Support;

class EvaluationCriteria
{
    /** Criteria a team lead can choose from when scoring a member's performance. */
    public const MEMBER_CRITERIA = [
        'Quality' => 'Output standard',
        'Productivity' => 'Work completed',
        'Teamwork' => 'Collaboration',
        'Communication' => 'Clarity',
        'Reliability' => 'Follow-through',
        'Initiative' => 'Self-direction',
        'Problem Solving' => 'Handling obstacles',
        'Time Management' => 'Meeting deadlines',
        'Attention to Detail' => 'Accuracy',
        'Adaptability' => 'Handling change',
    ];

    /** Criteria a member can choose from when scoring their team lead. */
    public const LEAD_CRITERIA = [
        'Leadership' => 'Direction',
        'Communication' => 'Clarity',
        'Support' => 'Helpfulness',
        'Organization' => 'Planning',
        'Fairness' => 'Consistency',
        'Mentorship' => 'Growth support',
        'Availability' => 'Responsiveness',
        'Decision Making' => 'Judgment',
        'Delegation' => 'Task distribution',
        'Recognition' => 'Acknowledging effort',
    ];

    /** Default label for each member-evaluation score column, keyed by column name. */
    public const MEMBER_DEFAULT_LABELS = [
        'quality_score' => 'Quality',
        'productivity_score' => 'Productivity',
        'teamwork_score' => 'Teamwork',
        'communication_score' => 'Communication',
        'reliability_score' => 'Reliability',
    ];

    /** Default label for each lead-evaluation score column, keyed by column name. */
    public const LEAD_DEFAULT_LABELS = [
        'leadership_score' => 'Leadership',
        'communication_score' => 'Communication',
        'support_score' => 'Support',
        'organization_score' => 'Organization',
        'fairness_score' => 'Fairness',
    ];
}
