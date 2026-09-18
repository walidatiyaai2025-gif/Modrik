<?php

namespace Tests\Feature;

use Tests\TestCase;

final class StudentReadinessAssessmentReconciliationTest extends TestCase
{
    public function test_assessment_runtime_is_reconciled_from_integrated_evidence_without_declaring_children_ready(): void
    {
        $path = base_path('../../governance/MODRIK_STUDENT_READINESS.json');
        $ledger = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $assessment = collect($ledger['domains'])->firstWhere('id', 'assessment');

        $this->assertSame(100, $assessment['percent']);
        $this->assertSame('pass', $assessment['status']);
        $this->assertSame('PASS', $ledger['mandatory_gates']['assessment_runtime']);
        $this->assertFalse($ledger['children_ready']);
        $this->assertSame(0, $ledger['overall_readiness_percent']);

        $evidence = collect($ledger['evidence'])->firstWhere('id', 'AL03_ASSESSMENT_RUNTIME');

        $this->assertSame(355, $evidence['owner_issue']);
        $this->assertSame(363, $evidence['integration_issue']);
        $this->assertSame('integrated', $evidence['status']);
        $this->assertSame(370, $evidence['core_pr']);
        $this->assertSame([375, 385, 389, 402], $evidence['hardening_prs']);
        $this->assertSame('9cefd3ec44f71e65a29e3f41d22ecd7c2380b1cd', $evidence['integrated_main_sha']);

        foreach ([
            'practice_session_creation',
            'server_selection_order_seed_authority',
            'immutable_same_attempt_resume',
            'answer_retry_idempotency',
            'backend_authoritative_scoring',
            'persistence_reread_before_success',
            'exam_no_hint_no_explanation_policy',
        ] as $fact) {
            $this->assertSame('pass', $evidence['facts'][$fact]);
        }

        $this->assertSame('fail_closed', $evidence['facts']['cross_user_direct_id_mutation']);
        $this->assertSame(402, $evidence['exact_head_ci']['recovery_pr']);
        $this->assertSame(1511, $evidence['exact_head_ci']['bootstrap_number']);
        $this->assertSame(206, $evidence['exact_head_ci']['unified_release_number']);
        $this->assertSame(550, $evidence['exact_head_ci']['demo_package_number']);
        $this->assertSame('success', $evidence['exact_head_ci']['conclusion']);

        $this->assertSame('blocked_owner_last', $ledger['mandatory_gates']['real_year6_pilot']);
        $this->assertSame('blocked_owner_last', $ledger['mandatory_gates']['real_year7_pilot']);
    }
}
