<?php

namespace Tests\Feature;

use Tests\TestCase;

final class StudentReadinessAssessmentReconciliationTest extends TestCase
{
    public function test_assessment_runtime_is_reconciled_from_integrated_evidence_without_declaring_children_ready(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));
        $assessment = $this->domainById($ledger, 'assessment');

        $this->assertSame(100, $assessment['percent'] ?? null);
        $this->assertSame('pass', $assessment['status'] ?? null);
        $this->assertSame('PASS', $ledger['mandatory_gates']['assessment_runtime'] ?? null);
        $this->assertFalse((bool) ($ledger['children_ready'] ?? true));
        $this->assertSame(44, $ledger['overall_readiness_percent'] ?? null);

        $evidence = $this->evidenceById($ledger, 'AL03_ASSESSMENT_RUNTIME');

        $this->assertSame(355, $evidence['owner_issue'] ?? null);
        $this->assertSame(363, $evidence['integration_issue'] ?? null);
        $this->assertSame('integrated', $evidence['status'] ?? null);
        $this->assertSame(370, $evidence['core_pr'] ?? null);
        $this->assertSame([375, 385, 389, 402], $evidence['hardening_prs'] ?? null);
        $this->assertSame('9cefd3ec44f71e65a29e3f41d22ecd7c2380b1cd', $evidence['integrated_main_sha'] ?? null);

        foreach ([
            'practice_session_creation',
            'server_selection_order_seed_authority',
            'immutable_same_attempt_resume',
            'answer_retry_idempotency',
            'backend_authoritative_scoring',
            'persistence_reread_before_success',
            'exam_no_hint_no_explanation_policy',
        ] as $fact) {
            $this->assertSame('pass', $evidence['facts'][$fact] ?? null);
        }

        $this->assertSame('fail_closed', $evidence['facts']['cross_user_direct_id_mutation'] ?? null);
        $this->assertSame(402, $evidence['exact_head_ci']['recovery_pr'] ?? null);
        $this->assertSame(1511, $evidence['exact_head_ci']['bootstrap_number'] ?? null);
        $this->assertSame(206, $evidence['exact_head_ci']['unified_release_number'] ?? null);
        $this->assertSame(550, $evidence['exact_head_ci']['demo_package_number'] ?? null);
        $this->assertSame('success', $evidence['exact_head_ci']['conclusion'] ?? null);
        $this->assertSame(1512, $evidence['exact_main_ci']['bootstrap_number'] ?? null);
        $this->assertSame(207, $evidence['exact_main_ci']['unified_release_number'] ?? null);
        $this->assertSame('skipped_on_push', $evidence['exact_main_ci']['dependency_review'] ?? null);
        $this->assertSame('success', $evidence['exact_main_ci']['conclusion'] ?? null);

        $this->assertSame('blocked_owner_last', $ledger['mandatory_gates']['real_year6_pilot'] ?? null);
        $this->assertSame('blocked_owner_last', $ledger['mandatory_gates']['real_year7_pilot'] ?? null);
    }

    /** @return array<string, mixed> */
    private function jsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'Unable to read '.$path);

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded, 'Expected JSON object in '.$path);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    private function domainById(array $ledger, string $id): array
    {
        $domains = $ledger['domains'] ?? null;
        $this->assertIsArray($domains);

        foreach ($domains as $domain) {
            if (is_array($domain) && ($domain['id'] ?? null) === $id) {
                return $domain;
            }
        }

        self::fail('Missing readiness domain '.$id);
    }

    /**
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    private function evidenceById(array $ledger, string $id): array
    {
        $evidence = $ledger['evidence'] ?? null;
        $this->assertIsArray($evidence);

        foreach ($evidence as $entry) {
            if (is_array($entry) && ($entry['id'] ?? null) === $id) {
                return $entry;
            }
        }

        self::fail('Missing readiness evidence '.$id);
    }
}
