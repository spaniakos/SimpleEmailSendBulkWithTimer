<?php

namespace App\Support;

use App\Models\SmtpConfiguration;

class EmailErrorSanitizer
{
    public function sanitize(string $message, ?SmtpConfiguration $smtpConfiguration): string
    {
        if (! $smtpConfiguration) {
            return $message;
        }

        $credentials = array_values(array_filter([
            $smtpConfiguration->username,
            $smtpConfiguration->password,
        ], static fn (?string $credential): bool => $credential !== null && $credential !== ''));

        return $credentials === []
            ? $message
            : str_replace($credentials, '[redacted]', $message);
    }
}
