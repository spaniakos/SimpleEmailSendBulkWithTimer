<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EmailDeliveryEvent;
use App\Models\EmailRecipient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class EmailDeliveryReportService
{
    private const TYPES = ['hard_bounce', 'soft_bounce', 'complaint', 'delivered', 'rejected'];

    public function __construct(private readonly DeliveryReportParser $parser)
    {
    }

    public function ingestRaw(string $raw): bool
    {
        $report = $this->parser->parse($raw);
        if ($report === null) {
            return false;
        }

        $reportId = hash('sha256', implode("\0", [
            $report['message_id'],
            $report['type'],
            $report['diagnostic'] ?? '',
            $report['feedback_type'] ?? '',
        ]));

        return $this->record(
            messageId: $report['message_id'],
            type: $report['type'],
            diagnostic: $report['diagnostic'],
            occurredAt: now(),
            reportId: $reportId,
        );
    }

    public function record(string $messageId, string $type, ?string $diagnostic, Carbon $occurredAt, string $reportId): bool
    {
        if (! in_array($type, self::TYPES, true) || ! preg_match('/^<[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+>$/', $messageId)) {
            return false;
        }

        $diagnostic = $this->cleanDiagnostic($diagnostic);
        if (! preg_match('/^[A-Za-z0-9._-]{1,64}$/', $reportId)) {
            return false;
        }

        try {
            return DB::transaction(function () use ($messageId, $type, $diagnostic, $occurredAt, $reportId): bool {
                if (EmailDeliveryEvent::where('report_id', $reportId)->exists()) {
                    return false;
                }

                $recipient = EmailRecipient::where('message_id', $messageId)
                    ->lockForUpdate()
                    ->first();

                if ($recipient === null || $recipient->status !== 'sent') {
                    return false;
                }

                $recipient->delivery_status = match ($type) {
                    'hard_bounce' => 'bounced',
                    'soft_bounce' => 'deferred',
                    'complaint' => 'complained',
                    'delivered' => 'delivered',
                    'rejected' => 'rejected',
                };
                $recipient->save();

                EmailDeliveryEvent::create([
                    'email_recipient_id' => $recipient->id,
                    'type' => $type,
                    'report_id' => $reportId,
                    'diagnostic' => $diagnostic,
                    'occurred_at' => $occurredAt,
                ]);

                if (in_array($type, ['hard_bounce', 'complaint'], true)) {
                    $contact = Contact::whereKey($recipient->contact_id)->lockForUpdate()->first();
                    if ($contact !== null && $contact->delivery_suppression_reason !== 'complaint') {
                        $contact->update([
                            'delivery_suppressed_at' => $contact->delivery_suppressed_at ?? $occurredAt,
                            'delivery_suppression_reason' => $type,
                        ]);
                    }
                }

                return true;
            }, 3);
        } catch (QueryException $exception) {
            // Unique report IDs make retries safe; malformed/unmatched external input is never logged.
            if (in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)) {
                return false;
            }

            throw $exception;
        }
    }

    private function cleanDiagnostic(?string $diagnostic): ?string
    {
        if ($diagnostic === null) {
            return null;
        }

        $diagnostic = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $diagnostic) ?? '';
        $diagnostic = trim($diagnostic);

        return $diagnostic === '' ? null : mb_substr($diagnostic, 0, 1000);
    }
}
