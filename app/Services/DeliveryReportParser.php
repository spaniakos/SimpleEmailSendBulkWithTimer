<?php

namespace App\Services;

use InvalidArgumentException;
use Throwable;
use ZBateson\MailMimeParser\MailMimeParser;

class DeliveryReportParser
{
    public const MAX_REPORT_BYTES = 1_048_576;

    /**
     * Parse a bounded RFC 3464 DSN or ARF abuse report into the fields needed for correlation.
     * The caller must discard the raw input after this method returns.
     *
     * @return array{message_id: string, type: string, diagnostic: ?string, feedback_type?: string}|null
     */
    public function parse(string $raw): ?array
    {
        if (strlen($raw) > self::MAX_REPORT_BYTES) {
            throw new InvalidArgumentException('Delivery report exceeds the maximum allowed size.');
        }

        try {
            $parser = new MailMimeParser;
            $message = $parser->parse($raw, false);
            $contentType = strtolower((string) $message->getHeaderValue('Content-Type'));

            if (str_starts_with($contentType, 'multipart/report') === false) {
                return null;
            }

            foreach ($message->getAllPartsByMimeType('message/delivery-status') as $part) {
                $fields = $this->parseFieldBlocks((string) $part->getContent());
                $messageId = $this->messageId($fields['original-message-id'] ?? $message->getHeaderValue('In-Reply-To'));
                $status = $fields['status'] ?? '';
                $action = strtolower($fields['action'] ?? '');
                $diagnostic = $this->diagnostic($fields['diagnostic-code'] ?? null);

                if ($messageId === null) {
                    continue;
                }

                if (str_starts_with($status, '4.') || $action === 'delayed') {
                    return ['message_id' => $messageId, 'type' => 'soft_bounce', 'diagnostic' => $diagnostic];
                }

                if (str_starts_with($status, '5.7.')) {
                    return ['message_id' => $messageId, 'type' => 'rejected', 'diagnostic' => $diagnostic];
                }

                if (str_starts_with($status, '5.') || $action === 'failed') {
                    return ['message_id' => $messageId, 'type' => 'hard_bounce', 'diagnostic' => $diagnostic];
                }

                if ($action === 'delivered' || $action === 'relayed' || $action === 'expanded') {
                    return ['message_id' => $messageId, 'type' => 'delivered', 'diagnostic' => $diagnostic];
                }
            }

            foreach ($message->getAllPartsByMimeType('message/feedback-report') as $part) {
                $fields = $this->parseFieldBlocks((string) $part->getContent());
                $messageId = $this->messageId($fields['original-message-id'] ?? null);

                if ($messageId !== null && isset($fields['feedback-type'])) {
                    return [
                        'message_id' => $messageId,
                        'type' => 'complaint',
                        'diagnostic' => null,
                        'feedback_type' => mb_substr($fields['feedback-type'], 0, 100),
                    ];
                }
            }
        } catch (Throwable) {
            // Reports are untrusted input. A malformed report is ignored without logging its content.
            return null;
        }

        return null;
    }

    /** @return array<string, string> */
    private function parseFieldBlocks(string $content): array
    {
        $fields = [];
        $lastField = null;

        foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $matches)) {
                $lastField = strtolower($matches[1]);
                $fields[$lastField] = trim($matches[2]);
            } elseif ($lastField !== null && preg_match('/^[ \t]+(.+)$/', $line, $matches)) {
                $fields[$lastField] .= ' '.trim($matches[1]);
            }
        }

        return $fields;
    }

    private function messageId(?string $value): ?string
    {
        if (is_string($value) === false || preg_match('/<[^<>\s@]+@[^<>\s@]+>/', $value, $matches)) {
            return null;
        }

        return $matches[0];
    }

    private function diagnostic(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/^[^;]+;\s*/', '', $value) ?? $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 1000);
    }
}
