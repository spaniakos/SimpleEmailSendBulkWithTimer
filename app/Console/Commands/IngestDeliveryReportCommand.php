<?php

namespace App\Console\Commands;

use App\Services\DeliveryReportParser;
use App\Services\EmailDeliveryReportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class IngestDeliveryReportCommand extends Command
{
    protected $signature = 'email:ingest-delivery-report';

    protected $description = 'Read one bounded DSN or abuse report from standard input and record its delivery event';

    public function handle(EmailDeliveryReportService $reports): int
    {
        $input = fopen('php://stdin', 'rb');
        if ($input === false) {
            $this->error('Unable to read delivery report input.');

            return self::FAILURE;
        }

        try {
            $raw = stream_get_contents($input, DeliveryReportParser::MAX_REPORT_BYTES + 1);
            if ($raw === false || strlen($raw) > DeliveryReportParser::MAX_REPORT_BYTES) {
                $this->error('Delivery report exceeds the maximum allowed size.');

                return self::FAILURE;
            }

            if (! $reports->ingestRaw($raw)) {
                $this->line('No matching supported delivery report was recorded.');

                return self::SUCCESS;
            }

            $this->info('Delivery feedback recorded.');

            return self::SUCCESS;
        } catch (InvalidArgumentException) {
            $this->error('Delivery report exceeds the maximum allowed size.');

            return self::FAILURE;
        } finally {
            fclose($input);
        }
    }
}
