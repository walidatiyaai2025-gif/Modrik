<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemUpdateUploadLimitTest extends TestCase
{
    public function test_update_center_transport_accepts_realistic_unified_release_sizes(): void
    {
        $maxPackageKb = (int) config('updates.max_package_kb');
        $temporaryRules = config('livewire.temporary_file_upload.rules');

        $this->assertGreaterThanOrEqual(64 * 1024, $maxPackageKb);
        $this->assertLessThanOrEqual(512 * 1024, $maxPackageKb);
        $this->assertIsArray($temporaryRules);
        $this->assertContains('required', $temporaryRules);
        $this->assertContains('file', $temporaryRules);
        $this->assertContains('max:'.$maxPackageKb, $temporaryRules);
    }
}
