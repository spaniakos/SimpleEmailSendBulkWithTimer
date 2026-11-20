<?php

namespace App\Http\Controllers;

use App\Models\EmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmailSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $settings = $request->user()->emailSettings;
        $rateLimits = array_merge(EmailSettings::rateLimitDefaults(), $settings?->only([
            'max_emails_per_window',
            'window_seconds',
            'max_emails_per_domain',
            'domain_window_seconds',
        ]) ?? []);

        return view('settings.email', [
            'footerText' => $settings?->unsubscribe_footer_text ?? EmailSettings::DEFAULT_UNSUBSCRIBE_FOOTER_TEXT,
            'rateLimits' => $rateLimits,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unsubscribe_footer_text' => ['required', 'string', 'max:500'],
            'max_emails_per_window' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'window_seconds' => ['sometimes', 'integer', 'min:1', 'max:86400'],
            'max_emails_per_domain' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'domain_window_seconds' => ['sometimes', 'integer', 'min:1', 'max:86400'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $settings = $request->user()->emailSettings;
            $rateLimits = array_merge(
                $settings?->only(array_keys(EmailSettings::rateLimitDefaults())) ?? EmailSettings::rateLimitDefaults(),
                array_intersect_key($validated, EmailSettings::rateLimitDefaults()),
            );
            $request->user()->emailSettings()->updateOrCreate([], array_merge(
                ['unsubscribe_footer_text' => $validated['unsubscribe_footer_text']],
                $rateLimits,
            ));
        });

        return redirect()->route('settings.email.edit');
    }
}
