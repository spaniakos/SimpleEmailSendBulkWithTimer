<?php

namespace Tests\Unit;

use App\Services\DeliveryReportParser;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryReportParserTest extends TestCase
{
    public function test_rfc3464_hard_bounce_is_correlated_by_original_message_id(): void
    {
        $rawReport = file_get_contents(base_path('tests/Fixtures/email/hard-bounce.eml'));

        $report = app(DeliveryReportParser::class)->parse($rawReport);

        $this->assertSame('<message-abc@example.test>', $report['message_id']);
        $this->assertSame('hard_bounce', $report['type']);
        $this->assertSame('550 5.1.1 No such user', $report['diagnostic']);
        $this->assertArrayNotHasKey('raw', $report);
    }

    public function test_rfc3464_temporary_failure_is_parsed_as_a_soft_bounce(): void
    {
        $rawReport = file_get_contents(base_path('tests/Fixtures/email/soft-bounce.eml'));

        $report = app(DeliveryReportParser::class)->parse($rawReport);

        $this->assertSame('<message-def@example.test>', $report['message_id']);
        $this->assertSame('soft_bounce', $report['type']);
        $this->assertSame('451 4.2.2 Mailbox temporarily full', $report['diagnostic']);
    }

    public function test_arf_abuse_report_is_parsed_as_a_complaint(): void
    {
        $rawReport = file_get_contents(base_path('tests/Fixtures/email/abuse-complaint.eml'));

        $report = app(DeliveryReportParser::class)->parse($rawReport);

        $this->assertSame('<message-ghi@example.test>', $report['message_id']);
        $this->assertSame('complaint', $report['type']);
        $this->assertSame('abuse', $report['feedback_type']);
    }

    public function test_malformed_and_oversized_reports_are_rejected_without_unbounded_parsing(): void
    {
        $parser = app(DeliveryReportParser::class);

        $this->assertNull($parser->parse("From: stranger@example.test\r\nSubject: not a delivery report\r\n\r\nhello"));
        $this->expectException(InvalidArgumentException::class);
        $parser->parse(str_repeat('x', DeliveryReportParser::MAX_REPORT_BYTES + 1));
    }
}
