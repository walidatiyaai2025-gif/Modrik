<?php

namespace App\Domain\Learning;

final class AdaptiveLearningContract
{
    /** @var list<string> */
    public const CURRICULUM_NODE_TYPES = ['subject', 'unit', 'topic', 'skill'];

    /** @var list<string> */
    public const LEARNING_OBJECTIVE_STATUSES = ['draft', 'published', 'retired'];

    /** @var list<string> */
    public const QUESTION_TYPES = [
        'multiple_choice',
        'true_false',
        'numeric',
        'fill_blank',
        'short_answer',
        'matching',
        'ordering',
        'multi_select',
        'image_question',
        'reading_comprehension',
        'multi_step_math',
    ];

    /** @var list<string> */
    public const QUESTION_PUBLICATION_STATUSES = [
        'draft',
        'imported',
        'needs_review',
        'approved',
        'published',
        'suspended',
        'archived',
        'rejected',
    ];

    /** @var list<string> */
    public const QUESTION_REVIEW_STATES = ['pending', 'needs_review', 'approved', 'rejected'];

    /** @var list<string> */
    public const QUESTION_DIFFICULTIES = ['Easy', 'Medium', 'Hard', 'Revision', 'Exam'];

    /** @var list<string> */
    public const QUESTION_GENERATION_KINDS = ['static', 'template'];

    private function __construct() {}
}
