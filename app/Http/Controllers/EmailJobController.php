<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailJob;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class EmailJobController extends Controller
{
    public function index()
    {
        $jobs = Auth::user()->emailJobs()->with('smtpConfiguration')->latest()->get();
        return view('jobs.index', compact('jobs'));
    }

    public function create()
    {
        $smtpConfigurations = Auth::user()->smtpConfigurations()->get();
        return view('jobs.create', compact('smtpConfigurations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'smtp_configuration_id' => 'required|exists:smtp_configurations,id',
            'subject' => 'required|string|max:255',
            'html_content' => ['required', 'string', function ($attribute, $value, $fail) {
                // Check if content is meaningful (not just empty tags)
                $stripped = strip_tags(trim($value));
                if (empty($stripped)) {
                    $fail('The email content cannot be empty.');
                }
            }],
            'csv_file' => 'nullable|file|mimes:csv,txt|max:10240',
            'csv_text' => 'nullable|string',
        ]);

        // Verify SMTP config belongs to user
        $smtpConfig = SmtpConfiguration::where('id', $validated['smtp_configuration_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Parse CSV
        $emails = $this->parseCsv($request);

        if (empty($emails)) {
            return back()->withErrors(['csv' => 'No valid email addresses found.'])->withInput();
        }

        // Create email job
        $emailJob = EmailJob::create([
            'user_id' => Auth::id(),
            'smtp_configuration_id' => $smtpConfig->id,
            'name' => $validated['name'] ?? 'Email Job ' . now()->format('Y-m-d H:i:s'),
            'subject' => $validated['subject'],
            'html_content' => $validated['html_content'],
            'status' => 'pending',
            'total_recipients' => count($emails),
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        // Create recipients
        foreach ($emails as $email) {
            $domain = $this->extractDomain($email);
            
            EmailRecipient::create([
                'email_job_id' => $emailJob->id,
                'email_address' => $email,
                'domain' => $domain,
                'status' => 'pending',
            ]);
        }

        // Dispatch single batch-processing job instead of N individual jobs
        ProcessEmailJob::dispatch($emailJob, $smtpConfig);

        return redirect()->route('jobs.show', $emailJob)
            ->with('success', 'Email job created successfully. Emails are being sent in batches.');
    }

    public function show(EmailJob $job)
    {
        $this->authorize('view', $job);
        
        $job->load(['recipients' => function ($query) {
            $query->latest();
        }, 'smtpConfiguration']);

        $emailJob = $job;
        return view('jobs.show', compact('emailJob'));
    }

    public function edit(EmailJob $job)
    {
        $this->authorize('update', $job);
        
        $smtpConfigurations = Auth::user()->smtpConfigurations()->get();
        $job->load('smtpConfiguration');
        
        return view('jobs.edit', compact('job', 'smtpConfigurations'));
    }

    public function update(Request $request, EmailJob $job)
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'smtp_configuration_id' => 'required|exists:smtp_configurations,id',
            'subject' => 'required|string|max:255',
            'html_content' => 'required|string',
        ]);

        // Verify SMTP config belongs to user
        $smtpConfig = SmtpConfiguration::where('id', $validated['smtp_configuration_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $job->update([
            'name' => $validated['name'] ?? $job->name,
            'smtp_configuration_id' => $smtpConfig->id,
            'subject' => $validated['subject'],
            'html_content' => $validated['html_content'],
        ]);

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Email job updated successfully.');
    }

    public function destroy(EmailJob $job)
    {
        $this->authorize('delete', $job);
        $job->delete();

        return redirect()->route('jobs.index')
            ->with('success', 'Email job deleted successfully.');
    }

    public function pause(EmailJob $emailJob)
    {
        $this->authorize('update', $emailJob);
        $emailJob->update(['status' => 'paused']);
        
        return back()->with('success', 'Job paused successfully.');
    }

    public function resume(EmailJob $emailJob)
    {
        $this->authorize('update', $emailJob);
        
        if ($emailJob->status !== 'paused') {
            return back()->withErrors(['status' => 'Job is not paused.']);
        }

        $emailJob->update(['status' => 'processing']);

        // Check if there are pending recipients
        $pendingCount = $emailJob->recipients()
            ->where('status', 'pending')
            ->count();

        if ($pendingCount > 0) {
            // Check if a job is already queued/processing for this email job
            $lockKey = "email_job_processing_{$emailJob->id}";
            if (!Cache::has($lockKey)) {
                // No job is currently processing, dispatch a new one
                ProcessEmailJob::dispatch($emailJob, $emailJob->smtpConfiguration);
            }
            // If lock exists, a job is already processing, so we don't need to dispatch another
        }

        return back()->with('success', 'Job resumed successfully.');
    }

    protected function parseCsv(Request $request): array
    {
        $emails = [];

        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $content = file_get_contents($file->getRealPath());
        } elseif ($request->filled('csv_text')) {
            $content = $request->csv_text;
        } else {
            return [];
        }

        // Parse CSV content
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Handle CSV format (comma or semicolon separated)
            $parts = str_getcsv($line);
            
            foreach ($parts as $part) {
                $part = trim($part);
                if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = strtolower($part);
                }
            }
        }

        // Remove duplicates
        return array_unique($emails);
    }

    protected function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return strtolower($parts[1] ?? 'unknown');
    }
}
