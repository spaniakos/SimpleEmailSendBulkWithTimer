<?php

namespace App\Http\Controllers;

use App\Services\UnsubscribeTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    public function confirm(string $token, UnsubscribeTokenService $tokens): View|Response
    {
        abort_unless($tokens->isValid($token), 410);

        return response()->view('unsubscribe.confirm')->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }

    public function consume(Request $request, string $token, UnsubscribeTokenService $tokens): Response
    {
        abort_unless($request->input('List-Unsubscribe') === 'One-Click', 400);
        abort_unless($tokens->consume($token), 410);

        return response()->view('unsubscribe.complete')->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }
}
