<?php

namespace App\Http\Controllers;

use App\Models\EmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $settings = $request->user()->emailSettings;

        return view('settings.email', [
            'footerText' => $settings?->unsubscribe_footer_text ?? EmailSettings::DEFAULT_UNSUBSCRIBE_FOOTER_TEXT,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unsubscribe_footer_text' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->emailSettings()->updateOrCreate([], $validated);

        return redirect('/settings/email');
    }
}
