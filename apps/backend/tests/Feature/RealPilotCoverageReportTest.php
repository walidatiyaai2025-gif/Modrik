<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RealPilotCoverageReportTest extends TestCase
{
    public function test_real_pilot_coverage_report_matches_integrated_source_registry_truth(): void
    {
        $manifest = $this->jsonFile(resource_path('pilot/real-pilot-materials.json'));
        $coverage = $this->jsonFile(base_path('../../governance/MODRIK_REAL_PILOT_COVERAGE.json'));

        $materials = $manifest['materials'] ?? null;
        $this->assertIsArray($materials);
        $summary = $coverage['summary'] ?? null;
        $this->assertIsArray($summary);

        $this->assertSame(count($materials), $summary['registered_sources'] ?? null);
        $this->assertSame(
            count(array_filter($materials, static fn (mixed $material): bool => is_array($material) && is_array($material['segments'] ?? null) && $material['segments'] !== [])),
            $summary['segmented_sources'] ?? null,
        );
        $this->assertSame(
            count(array_filter($materials, static fn (mixed $material): bool => is_array($material) && ($material['rights']['status'] ?? null) === 'pending_review')),
            $summary['rights_pending_sources'] ?? null,
        );
        $this->assertSame(
            count(array_filter($materials, static fn (mixed $material): bool => is_array($material) && ($material['curriculum_mapping']['status'] ?? null) === 'mapping_required')),
            $summary['mapping_required_sources'] ?? null,
        );
        $this->assertSame(
            count(array_filter($materials, static fn (mixed $material): bool => is_array($material) && ($material['curriculum_mapping']['status'] ?? null) === 'partial_source_verified')),
            $summary['partial_source_verified_sources'] ?? null,
        );
        $this->assertSame(
            count(array_filter($materials, static fn (mixed $material): bool => is_array($material) && ($material['privacy']['status'] ?? null) === 'pii_redaction_required')),
            $summary['pii_redaction_required_sources'] ?? null,
        );
        $this->assertSame(
            count(array_filter($materials, static fn (mixed $material): bool => is_array($material) && ($material['delivery_eligible'] ?? false) === true)),
            $summary['student_delivery_eligible_sources'] ?? null,
        );

        $this->assertSame('BLOCKED_OWNER_LAST', $coverage['status'] ?? null);
        $this->assertSame(0, $summary['student_delivery_eligible_sources'] ?? null);
    }

    public function test_year6_and_year7_acceptance_states_do_not_invent_scope_rights_or_source_material(): void
    {
        $manifest = $this->jsonFile(resource_path('pilot/real-pilot-materials.json'));
        $coverage = $this->jsonFile(base_path('../../governance/MODRIK_REAL_PILOT_COVERAGE.json'));

        $year6 = $coverage['pilot_years']['Year 6'] ?? null;
        $year7 = $coverage['pilot_years']['Year 7'] ?? null;
        $this->assertIsArray($year6);
        $this->assertIsArray($year7);

        $grade6 = $this->materialById($manifest, 'pilot-y6-arabic-kuwait-2025-2026-t2-part1');
        $grade6Mapping = $grade6['curriculum_mapping'] ?? null;
        $grade6Rights = $grade6['rights'] ?? null;
        $this->assertIsArray($grade6Mapping);
        $this->assertIsArray($grade6Rights);

        $this->assertSame('Grade 6', $grade6['source_claims']['year_level'] ?? null);
        $this->assertSame('Arabic', $grade6['subject'] ?? null);
        $this->assertSame('partial_source_verified', $grade6Mapping['status'] ?? null);
        $this->assertNull($grade6Mapping['track_reference'] ?? null);
        $this->assertSame('pending_review', $grade6Rights['status'] ?? null);

        $this->assertSame('PARTIAL_PASS', $year6['source_evidence_status'] ?? null);
        $this->assertSame('BLOCKED_OWNER_LAST', $year6['academic_scope_status'] ?? null);
        $this->assertSame('BLOCKED_OWNER_LAST', $year6['rights_status'] ?? null);
        $this->assertSame('NOT_EVIDENCED', $year6['published_skill_coverage_status'] ?? null);
        $this->assertSame('NOT_EVIDENCED', $year6['published_question_coverage_status'] ?? null);

        $year7Sources = array_values(array_filter(
            $manifest['materials'] ?? [],
            static fn (mixed $material): bool => is_array($material)
                && in_array(($material['source_claims']['year_level'] ?? null), ['Grade 7', 'Year 7'], true),
        ));
        $this->assertSame([], $year7Sources);
        $this->assertSame([], $year7['source_ids'] ?? null);
        $this->assertSame('BLOCKED_OWNER_LAST', $year7['source_evidence_status'] ?? null);
        $this->assertSame('NOT_EVIDENCED', $year7['published_question_coverage_status'] ?? null);
    }

    public function test_unmapped_images_remain_unmapped_and_pii_scan_remains_blocked(): void
    {
        $manifest = $this->jsonFile(resource_path('pilot/real-pilot-materials.json'));
        $coverage = $this->jsonFile(base_path('../../governance/MODRIK_REAL_PILOT_COVERAGE.json'));

        $unmapped = $coverage['unmapped_sources'] ?? null;
        $this->assertIsArray($unmapped);

        foreach ($unmapped as $row) {
            $this->assertIsArray($row);
            $this->assertNull($row['target_year'] ?? null);
            $this->assertSame('MAPPING_REQUIRED', $row['status'] ?? null);
        }

        $fanboys = $this->materialById($manifest, 'pilot-en-fanboys-completed-worksheet');
        $this->assertSame('pii_redaction_required', $fanboys['privacy']['status'] ?? null);

        $grade5 = $this->materialById($manifest, 'pilot-math-subtracting-integers-grade5');
        $this->assertSame('Grade 5', $grade5['content_summary']['source_grade_label'] ?? null);

        $truth = $coverage['truth_boundary'] ?? null;
        $this->assertIsArray($truth);
        foreach ([
            'rights_approval_fabricated',
            'year7_source_fabricated',
            'academic_scope_fabricated',
            'published_real_question_content_claimed',
            'children_ready_claimed',
        ] as $key) {
            $this->assertFalse((bool) ($truth[$key] ?? true), $key.' must remain false');
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
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function materialById(array $manifest, string $sourceId): array
    {
        $materials = $manifest['materials'] ?? null;
        $this->assertIsArray($materials);

        foreach ($materials as $material) {
            if (is_array($material) && ($material['source_id'] ?? null) === $sourceId) {
                return $material;
            }
        }

        self::fail('Missing pilot material '.$sourceId);
    }
}
