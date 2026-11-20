<?php

namespace Tests\Unit;

use App\Services\DeliveryReportParser;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryReportParserTest extends TestCase
{
    public function test_rfc3464_hard_bounce_is_correlated_by_original_message_id(): void
    {
        $rawReport = <<<'MIME'
From: Mail Delivery Subsystem <mailer-daemon@example.test>
To: bounce@example.test
Subject: Delivery Status Notification (Failure)
MIME-Version: 1.0
Content-Type: multipart/report; report-type=delivery-status; boundary="dsn-boundary"

--dsn-boundary
Content-Type: text/plain; charset=utf-8

The message could not be delivered.
--dsn-boundary
Content-Type: message/delivery-status

Reporting-MTA: dns; mx.example.test
Original-Message-ID: <message-abc@example.test>

Final-Recipient: rfc822; missing@example.test
Action: failed
Status: 5.1.1
Diagnostic-Code: smtp; 550 5.1.1 No such user
--dsn-boundary--
MIME;

        $report = app(DeliveryReportParser::class)->parse($rawReport);

        $this->assertSame('<message-abc@example.test>', $report['message_id']);
        $this->assertSame('hard_bounce', $report['type']);
        $this->assertSame('550 5.1.1 No such user', $report['diagnostic']);
        $this->assertArrayNotHasKey('raw', $report);
    }

    public function test_rfc3464_temporary_failure_is_parsed_as_a_soft_bounce(): void
    {
        $rawReport = <<<'MIME'
From: Mail Delivery Subsystem <mailer-daemon@example.test>
Subject: Delivery Status Notification (Delay)
MIME-Version: 1.0
Content-Type: multipart/report; report-type=delivery-status; boundary="dsn-boundary"

--dsn-boundary
Content-Type: text/plain; charset=utf-8

Delivery is delayed.
--dsn-boundary
Content-Type: message/delivery-status

Original-Message-ID: <message-def@example.test>
Final-Recipient: rfc822; full@example.test
Action: delayed
Status: 4.2.2
Diagnostic-Code: smtp; 451 4.2.2 Mailbox temporarily full
--dsn-boundary--
MIME;

        $report = app(DeliveryReportParser::class)->parse($rawReport);

        $this->assertSame('<message-def@example.test>', $report['message_id']);
        $this->assertSame('soft_bounce', $report['type']);
        $this->assertSame('451 4.2.2 Mailbox temporarily full', $report['diagnostic']);
    }

    public function test_arf_abuse_report_is_parsed_as_a_complaint(): void
    {
        $rawReport = <<<'MIME'
From: Feedback Loop <fbl@example.test>
Subject: Abuse report
MIME-Version: 1.0
Content-Type: multipart/report; report-type=feedback-report; boundary="arf-boundary"

--arf-boundary
Content-Type: text/plain; charset=utf-8

This is a complaint.
--arf-boundary
Content-Type: message/feedback-report

Feedback-Type: abuse
User-Agent: Example FBL
Original-Mail-From: sender@example.test
Original-Rcpt-To: rfc822; reader@example.test
Original-Message-ID: <message-ghi@example.test>
--arf-boundary--
MIME;

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
