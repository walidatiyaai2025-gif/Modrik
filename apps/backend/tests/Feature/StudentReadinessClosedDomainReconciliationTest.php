<?php

namespace Tests\Feature;

use Tests\TestCase;

final class StudentReadinessClosedDomainReconciliationTest extends TestCase
{
    public function test_closed_domains_receive_only_evidence_backed_credit(): void
    {
        $ledger = $this->jsonFile(base_path('../../governance/MODRIK_STUDENT_READINESS.json'));

        self::assertSame(98, $ledger['overall_readiness_percent'] ?? null);
        self::assertFalse((bool) ($ledger['children_ready'] ?? true));

        foreach (['foundation', 'question_content', 'assessment', 'mastery_adaptive', 'revision_plan', 'student_ux', 'parent', 'rtl_accessibility', 'operations', 'quality_security'] as $id) {
            $domain = $this->domainById($ledger, $id);
            self::assertSame(100, $domain['percent'] ?? null, $id);
            self::assertSame('pass', $domain['status'] ?? null, $id);
        }

        self::assertSame('PASS', $ledger['mandatory_gates']['admin_controls'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['identity_academic_context'] ?? null);
        self::assertSame('blocked_owner_last', $ledger['mandatory_gates']['curriculum_content'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['question_bank'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['mastery_adaptive'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['student_ux'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['parent_ux'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['arabic_english_accessibility'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['security_integrity_qa'] ?? null);
        self::assertSame('PASS', $ledger['mandatory_gates']['exact_main_governed_ci_green'] ?? null);
        self::assertSame('blocked_owner_last', $ledger['mandatory_gates']['open_mandatory_learning_blockers_zero'] ?? null);
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
        self::assertSame('credited_with_AL05_after_357_closure', $mastery['domain_credit'] ?? null);
        self::assertSame(1525, $mastery['exact_main_ci']['bootstrap_number'] ?? null);

        $adaptive = $this->evidenceById($ledger, 'AL05_ADAPTIVE_STUDY');
        self::assertSame(357, $adaptive['owner_issue'] ?? null);
        self::assertSame([410, 411, 412, 413, 414], $adaptive['prs'] ?? null);
        self::assertSame('1c4d5c0b6f913c6c8da0d17534a3f4b5e110ff5b', $adaptive['integrated_main_sha'] ?? null);
        self::assertSame(1556, $adaptive['final_exact_head_ci']['bootstrap_number'] ?? null);
        self::assertSame('success', $adaptive['final_exact_head_ci']['conclusion'] ?? null);
        self::assertSame(false, $adaptive['facts']['runtime_paid_ai_required'] ?? null);

        $operations = $this->evidenceById($ledger, 'AL09_OPERATIONS');
        self::assertSame(408, $operations['pr'] ?? null);
        self::assertSame('756dec118b8f0d21bc2c30d8ec7783bd1a074783', $operations['integrated_main_sha'] ?? null);
        self::assertSame(1540, $operations['exact_head_ci']['bootstrap_number'] ?? null);
        self::assertSame(1541, $operations['exact_main_ci']['bootstrap_number'] ?? null);
        self::assertSame('blocked_dependency', $operations['facts']['adaptive_jobs_without_357'] ?? null);

        $studentUx = $this->evidenceById($ledger, 'AL06_STUDENT_UX');
        self::assertSame(358, $studentUx['owner_issue'] ?? null);
        self::assertSame(363, $studentUx['integration_issue'] ?? null);
        self::assertSame('integrated', $studentUx['status'] ?? null);
        self::assertSame([406, 407, 416, 417, 421, 423, 424], $studentUx['prs'] ?? null);
        self::assertSame([422], $studentUx['upstream_dependency_prs'] ?? null);
        self::assertSame('e886bf190f63695b0cd26c2127042d7e7630950c', $studentUx['integrated_main_sha'] ?? null);
        self::assertSame('pass', $studentUx['facts']['real_backend_session_boundary'] ?? null);
        self::assertSame('pass', $studentUx['facts']['authoritative_adaptive_home_today_mission_needs_practice_mistakes'] ?? null);
        self::assertSame('pass', $studentUx['facts']['persisted_student_text_size_preference_web_mobile'] ?? null);
        self::assertSame('absent', $studentUx['facts']['client_scoring_mastery_planning_authority'] ?? null);
        self::assertSame(false, $studentUx['facts']['runtime_paid_ai_required'] ?? null);
        self::assertSame(424, $studentUx['final_exact_head_ci']['pr'] ?? null);
        self::assertSame(1619, $studentUx['final_exact_head_ci']['bootstrap_number'] ?? null);
        self::assertSame(164, $studentUx['final_exact_head_ci']['mobile_native_compile_number'] ?? null);
        self::assertSame('success', $studentUx['final_exact_head_ci']['conclusion'] ?? null);

        $parent = $this->evidenceById($ledger, 'AL08_PARENT_ANALYTICS');
        self::assertSame(360, $parent['owner_issue'] ?? null);
        self::assertSame(363, $parent['integration_issue'] ?? null);
        self::assertSame('integrated', $parent['status'] ?? null);
        self::assertSame(426, $parent['pr'] ?? null);
        self::assertSame('90d129787101f6db96bbef3d78c6c593031f622a', $parent['integrated_main_sha'] ?? null);
        self::assertSame('pass', $parent['facts']['parent_child_authorization'] ?? null);
        self::assertSame('fail_closed', $parent['facts']['direct_id_idor'] ?? null);
        self::assertSame('absent', $parent['facts']['sibling_ranking'] ?? null);
        self::assertSame('pass', $parent['facts']['responsive_parent_browser_matrix'] ?? null);
        self::assertSame(1653, $parent['final_exact_head_ci']['bootstrap_number'] ?? null);
        self::assertSame(166, $parent['final_exact_head_ci']['web_portals_runtime_number'] ?? null);
        self::assertSame('success', $parent['final_exact_head_ci']['conclusion'] ?? null);

        $rtlAccessibility = $this->evidenceById($ledger, 'AL07_RTL_ACCESSIBILITY');
        self::assertSame(359, $rtlAccessibility['owner_issue'] ?? null);
        self::assertSame(363, $rtlAccessibility['integration_issue'] ?? null);
        self::assertSame('integrated', $rtlAccessibility['status'] ?? null);
        self::assertSame([366, 367], $rtlAccessibility['prior_prs'] ?? null);
        self::assertSame(428, $rtlAccessibility['final_pr'] ?? null);
        self::assertSame('0ccc0e371c5183db7302a8e22670ae22bf5985b6', $rtlAccessibility['integrated_main_sha'] ?? null);
        self::assertSame('pass', $rtlAccessibility['facts']['mixed_direction_math_isolation_web'] ?? null);
        self::assertSame('pass', $rtlAccessibility['facts']['persisted_small_normal_large_extra_large_web_flutter'] ?? null);
        self::assertSame('pass', $rtlAccessibility['facts']['student_question_minimum_18px_web_flutter'] ?? null);
        self::assertSame('pass', $rtlAccessibility['facts']['student_360_390_412_tablet_desktop_browser_acceptance'] ?? null);
        self::assertSame('pass', $rtlAccessibility['facts']['parent_360_390_412_tablet_desktop_browser_acceptance'] ?? null);
        self::assertSame('pass', $rtlAccessibility['facts']['admin_rtl_large_text_browser_acceptance'] ?? null);
        self::assertSame(1665, $rtlAccessibility['final_exact_head_ci']['bootstrap_number'] ?? null);
        self::assertSame(6, $rtlAccessibility['final_exact_head_ci']['rtl_accessibility_number'] ?? null);
        self::assertSame(329, $rtlAccessibility['final_exact_head_ci']['admin_ux_number'] ?? null);
        self::assertSame(172, $rtlAccessibility['final_exact_head_ci']['web_portals_runtime_number'] ?? null);
        self::assertSame(170, $rtlAccessibility['final_exact_head_ci']['mobile_native_compile_number'] ?? null);
        self::assertSame('success', $rtlAccessibility['final_exact_head_ci']['conclusion'] ?? null);
        $qa = $this->evidenceById($ledger, 'AL11_CROSS_DOMAIN_QA');
        self::assertSame(363, $qa['owner_issue'] ?? null);
        self::assertSame('reconciled', $qa['status'] ?? null);
        self::assertSame(1669, $qa['source_ci']['bootstrap_number'] ?? null);
        self::assertSame('success', $qa['source_ci']['bootstrap_conclusion'] ?? null);
        self::assertSame('success', $qa['source_ci']['pilot_strict'] ?? null);
        self::assertSame('pass', $qa['facts']['csrf_same_origin_mutation'] ?? null);
        self::assertSame('fail_closed', $qa['facts']['cross_user_direct_id_idor'] ?? null);
        self::assertSame('absent', $qa['facts']['production_child_pii_repository_fixture'] ?? null);
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
