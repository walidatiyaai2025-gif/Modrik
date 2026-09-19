<?php

namespace Tests\Feature;

use Tests\TestCase;

final class StudentReadinessClosedDomainReconciliationTest extends TestCase
{
    public function test_closed_domains_receive_only_evidence_backed_credit(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));

        self::assertSame(44, $ledger['overall_readiness_percent'] ?? null);
        self::assertFalse((bool) ($ledger['children_ready'] ?? true));

        foreach (['foundation', 'question_content', 'assessment', 'operations'] as $id) {
            $domain = $this->domainById($ledger, $id);
            self::assertSame(100, $domain['percent'] ?? null, $id);
            self::assertSame('pass', $domain['status'] ?? null, $id);
        }

        $masteryAdaptive = $this->domainById($ledger, 'mastery_adaptive');
        self::assertSame(0, $masteryAdaptive['percent'] ?? null);
        self::assertSame('not_evidenced', $masteryAdaptive['status'] ?? null);

        self::assertSame('PASS', $ledger['mandatory_gates']['admin_controls'] ?? null);
        self::assertSame('not_evidenced', $ledger['mandatory_gates']['question_bank'] ?? null);
        self::assertSame('not_evidenced', $ledger['mandatory_gates']['mastery_adaptive'] ?? null);
        self::assertSame('blocked_owner_last', $ledger['mandatory_gates']['real_year6_pilot'] ?? null);
        self::assertSame('blocked_owner_last', $ledger['mandatory_gates']['real_year7_pilot'] ?? null);
    }

    public function test_closed_domain_evidence_is_bound_to_integrated_green_main(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));

        $foundation = $this->evidenceById($ledger, 'AL01_FOUNDATION');
        self::assertSame([365, 368, 393], $foundation['prs'] ?? null);
        self::assertSame('aca4412919f1ca26cc3414a253b96d722b4a3ef2', $foundation['integrated_main_sha'] ?? null);
        self::assertSame(1490, $foundation['exact_main_ci']['bootstrap_number'] ?? null);
        self::assertSame('success', $foundation['exact_main_ci']['conclusion'] ?? null);

        $content = $this->evidenceById($ledger, 'AL02_QUESTION_CONTENT');
        self::assertSame(354, $content['owner_issue'] ?? null);
        self::assertSame('f791100aa41a79a6c56f17644b2cd1c229fbb3b2', $content['integrated_main_sha'] ?? null);
        self::assertSame(1501, $content['exact_main_ci']['bootstrap_number'] ?? null);
        self::assertSame(false, $content['facts']['runtime_paid_ai_required'] ?? null);

        $mastery = $this->evidenceById($ledger, 'AL04_MASTERY_ENGINE');
        self::assertSame(404, $mastery['pr'] ?? null);
        self::assertSame('withheld_until_357_adaptive_study_is_integrated', $mastery['domain_credit'] ?? null);
        self::assertSame(1525, $mastery['exact_main_ci']['bootstrap_number'] ?? null);

        $operations = $this->evidenceById($ledger, 'AL09_OPERATIONS');
        self::assertSame(408, $operations['pr'] ?? null);
        self::assertSame('756dec118b8f0d21bc2c30d8ec7783bd1a074783', $operations['integrated_main_sha'] ?? null);
        self::assertSame(1540, $operations['exact_head_ci']['bootstrap_number'] ?? null);
        self::assertSame(1541, $operations['exact_main_ci']['bootstrap_number'] ?? null);
        self::assertSame('blocked_dependency', $operations['facts']['adaptive_jobs_without_357'] ?? null);
    }

    /** @return array<string, mixed> */
    private function jsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents, 'Unable to read '.$path);

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, 'Expected JSON object in '.$path);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    private function domainById(array $ledger, string $id): array
    {
        $domains = $ledger['domains'] ?? null;
        self::assertIsArray($domains);

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
        self::assertIsArray($evidence);

        foreach ($evidence as $entry) {
            if (is_array($entry) && ($entry['id'] ?? null) === $id) {
                return $entry;
            }
        }

        self::fail('Missing readiness evidence '.$id);
    }
}
