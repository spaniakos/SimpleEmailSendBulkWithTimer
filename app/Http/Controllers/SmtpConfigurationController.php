<?php

namespace App\Http\Controllers;

use App\Models\SmtpConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmtpConfigurationController extends Controller
{
    public function index()
    {
        $configurations = Auth::user()->smtpConfigurations()->latest()->get();

        return view('smtp.index', compact('configurations'));
    }

    public function show(SmtpConfiguration $smtpConfiguration)
    {
        $this->authorize('view', $smtpConfiguration);

        return view('smtp.show', compact('smtpConfiguration'));
    }

    public function create()
    {
        return view('smtp.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'encryption' => 'required|in:tls,ssl',
            'from_email' => 'required|email|max:255',
            'from_name' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = Auth::id();

        SmtpConfiguration::create($validated);

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP configuration created successfully.');
    }

    public function edit(SmtpConfiguration $smtpConfiguration)
    {
        $this->authorize('update', $smtpConfiguration);

        return view('smtp.edit', compact('smtpConfiguration'));
    }

    public function update(Request $request, SmtpConfiguration $smtpConfiguration)
    {
        $this->authorize('update', $smtpConfiguration);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'encryption' => 'required|in:tls,ssl',
            'from_email' => 'required|email|max:255',
            'from_name' => 'nullable|string|max:255',
        ]);

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $smtpConfiguration->update($validated);

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP configuration updated successfully.');
    }

    public function destroy(SmtpConfiguration $smtpConfiguration)
    {
        $this->authorize('delete', $smtpConfiguration);
        $smtpConfiguration->delete();

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP configuration deleted successfully.');
    }

    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'encryption' => 'required|in:tls,ssl',
            'from_email' => 'required|email|max:255',
            'smtp_configuration_id' => 'nullable|exists:smtp_configurations,id',
        ]);

        // If password is not provided and we have an existing config, use the stored password
        if (empty($validated['password']) && ! empty($validated['smtp_configuration_id'])) {
            $existingConfig = SmtpConfiguration::where('id', $validated['smtp_configuration_id'])
                ->where('user_id', Auth::id())
                ->first();

            if ($existingConfig) {
                $validated['password'] = $existingConfig->password; // Already decrypted by model accessor
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is required for testing.',
                ], 400);
            }
        }

        if (empty($validated['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Password is required for testing.',
            ], 400);
        }

        try {
            // Configure mailer dynamically
            config([
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => $validated['host'],
                    'port' => $validated['port'],
                    'encryption' => $validated['encryption'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                    'timeout' => 10,
                ],
            ]);

            // Try to send a test email to the from_email address using SMTP mailer explicitly
            \Illuminate\Support\Facades\Mail::mailer('smtp')->raw('SMTP Connection Test', function ($message) use ($validated) {
                $message->to($validated['from_email'])
                    ->subject('SMTP Connection Test')
                    ->from($validated['from_email']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Connection successful! SMTP configuration is working correctly.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ], 400);
        }
    }
}
