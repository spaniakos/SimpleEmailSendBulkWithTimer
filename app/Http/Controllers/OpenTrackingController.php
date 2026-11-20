<?php

namespace App\Http\Controllers;

use App\Models\EmailRecipient;
use Illuminate\Http\Response;

class OpenTrackingController extends Controller
{
    public function show(string $token): Response
    {
        EmailRecipient::query()
            ->where('open_tracking_token_hash', hash('sha256', $token))
            ->where('status', 'sent')
            ->whereNull('opened_at')
            ->whereHas('emailJob', fn ($query) => $query->where('track_opens', true))
            ->update([
                'opened_at' => now(),
                'updated_at' => now(),
            ]);

        $pixel = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', true);

        return response($pixel, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Pragma' => 'no-cache',
        ]);
    }
}
