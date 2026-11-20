<?php

namespace Tests\Feature;

use Tests\TestCase;

class LicenseMetadataTest extends TestCase
{
    public function test_project_metadata_identifies_the_public_agplv3_composer_project(): void
    {
        $metadata = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('spaniakos/simple-email-send-bulk-with-timer', $metadata['name']);
        $this->assertSame('project', $metadata['type']);
        $this->assertSame('AGPL-3.0-only', $metadata['license']);
        $this->assertSame('^8.3', $metadata['require']['php']);
    }

    public function test_agpl_license_and_separate_commercial_license_notice_are_present(): void
    {
        $license = file_get_contents(base_path('LICENSE'));
        $commercialNotice = file_get_contents(base_path('COMMERCIAL-LICENSE.md'));

        $this->assertStringContainsString('GNU AFFERO GENERAL PUBLIC LICENSE', $license);
        $this->assertStringContainsString('Version 3, 19 November 2007', $license);
        $this->assertStringContainsString('separate commercial license', strtolower($commercialNotice));
        $this->assertStringContainsString('AGPL-3.0-only', $commercialNotice);
    }
}
