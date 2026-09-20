<?php

namespace Tests\Feature;

use Tests\TestCase;

final class StudentReadinessRealPilotReconciliationTest extends TestCase
{
    public function test_l11_reconciliation_records_blockers_without_inventing_readiness_credit(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));
        $coverage = $this->jsonFile(base_path('../../governance/MODRIK_REAL_PILOT_COVERAGE.json'));

        $this->assertSame('PARTIALLY_RECONCILED', $ledger['status'] ?? null);
        $this->assertFalse((bool) ($ledger['children_ready'] ?? true));
        $this->assertSame(87, $ledger['overall_readiness_percent'] ?? null);

        $realPilot = $this->domainById($ledger, 'real_pilot');
        $this->assertSame(0, $realPilot['percent'] ?? null);
        $this->assertSame('blocked_owner_last', $realPilot['status'] ?? null);

        $gates = $ledger['mandatory_gates'] ?? null;
        $this->assertIsArray($gates);
        $this->assertSame('blocked_owner_last', $gates['real_year6_pilot'] ?? null);
        $this->assertSame('blocked_owner_last', $gates['real_year7_pilot'] ?? null);
        $this->assertSame('blocked_dependency', $gates['parent_real_pilot'] ?? null);
        $this->assertSame('not_evidenced', $gates['exact_main_governed_ci_green'] ?? null);

        $this->assertSame('BLOCKED_OWNER_LAST', $coverage['status'] ?? null);
        $this->assertSame(0, $coverage['summary']['student_delivery_eligible_sources'] ?? null);
    }

    public function test_l11_evidence_is_bound_to_integrated_prs_and_green_ci(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));

        $registry = $this->evidenceById($ledger, 'L11_REAL_PILOT_SOURCE_REGISTRY');
        $this->assertSame(399, $registry['pr'] ?? null);
        $this->assertSame('db3d75b67a6046d1fff3f89d8a011f0e3a15f6eb', $registry['integrated_main_sha'] ?? null);
        $this->assertSame('success', $registry['exact_head_ci']['conclusion'] ?? null);
        $this->assertSame('success', $registry['exact_main_ci']['conclusion'] ?? null);

        $coverage = $this->evidenceById($ledger, 'L11_REAL_PILOT_COVERAGE');
        $this->assertSame(400, $coverage['pr'] ?? null);
        $this->assertSame('00278c827f6030d957c5f519e3b384fca1f64426', $coverage['integrated_main_sha'] ?? null);
        $this->assertSame('success', $coverage['exact_head_ci']['conclusion'] ?? null);
        $this->assertSame('success', $coverage['exact_main_ci']['conclusion'] ?? null);
        $this->assertSame('blocked_owner_last', $coverage['status'] ?? null);
    }

    public function test_l11_reconciliation_does_not_mutate_unreconciled_domain_credit(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));
        $domains = $ledger['domains'] ?? null;
        $this->assertIsArray($domains);

        foreach ($domains as $domain) {
            $this->assertIsArray($domain);
            $id = $domain['id'] ?? null;
            if ($id === 'real_pilot') {
                continue;
            }

            if (in_array($id, ['foundation', 'question_content', 'assessment', 'mastery_adaptive', 'revision_plan', 'student_ux', 'parent', 'operations'], true)) {
                $this->assertSame(100, $domain['percent'] ?? null);
                $this->assertSame('pass', $domain['status'] ?? null);

                continue;
            }

            $this->assertSame(0, $domain['percent'] ?? null);
            $this->assertSame('not_evidenced', $domain['status'] ?? null);
        }
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
