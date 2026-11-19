<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailJob;
use App\Models\EmailJob;
use App\Models\EmailRecipient;
use App\Models\SmtpConfiguration;
use App\Support\EmailErrorSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $mailingLists = Auth::user()->mailingLists()
            ->withCount(['contacts as subscribed_contacts_count' => fn ($query) => $query->whereNull('unsubscribed_at')])
            ->orderBy('name')
            ->get();

        return view('jobs.create', compact('smtpConfigurations', 'mailingLists'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'smtp_configuration_id' => 'required|exists:smtp_configurations,id',
            'mailing_list_id' => ['required', 'integer'],
            'subject' => 'required|string|max:255',
            'html_content' => ['required', 'string', function ($attribute, $value, $fail) {
                // Check if content is meaningful (not just empty tags)
                $stripped = strip_tags(trim($value));
                if (empty($stripped)) {
                    $fail('The email content cannot be empty.');
                }
            }],
        ]);

        // Verify SMTP config belongs to user
        $smtpConfig = SmtpConfiguration::where('id', $validated['smtp_configuration_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Scope list lookup to the authenticated owner to prevent cross-account sends.
        $mailingList = Auth::user()->mailingLists()->findOrFail($validated['mailing_list_id']);
        $contacts = $mailingList->contacts()
            ->whereNull('unsubscribed_at')
            ->orderBy('contacts.id')
            ->get();

        if ($contacts->isEmpty()) {
            return back()->withErrors(['mailing_list_id' => 'The selected list has no subscribed contacts.'])->withInput();
        }

        $emailJob = DB::transaction(function () use ($validated, $smtpConfig, $mailingList, $contacts): EmailJob {
            $emailJob = EmailJob::create([
                'user_id' => Auth::id(),
                'smtp_configuration_id' => $smtpConfig->id,
                'mailing_list_id' => $mailingList->id,
                'name' => $validated['name'] ?? 'Email Job '.now()->format('Y-m-d H:i:s'),
                'subject' => $validated['subject'],
                'html_content' => $validated['html_content'],
                'status' => 'pending',
                'total_recipients' => $contacts->count(),
                'sent_count' => 0,
                'failed_count' => 0,
            ]);

            foreach ($contacts as $contact) {
                EmailRecipient::create([
                    'email_job_id' => $emailJob->id,
                    'contact_id' => $contact->id,
                    'email_address' => $contact->email_address,
                    'domain' => $this->extractDomain($contact->email_address),
                    'status' => 'pending',
                ]);
            }

            return $emailJob;
        });

        // Dispatch single batch-processing job instead of N individual jobs
        ProcessEmailJob::dispatch($emailJob, $smtpConfig);

        return redirect()->route('jobs.show', $emailJob)
            ->with('success', 'Email job created successfully. Emails are being sent in batches.');
    }

    public function show(EmailJob $job)
    {
        $this->authorize('view', $job);

        $job->load(['recipients' => function ($query) {
            $query->orderBy('id', 'asc');
        }, 'smtpConfiguration']);

        $errorSanitizer = app(EmailErrorSanitizer::class);
        $job->recipients->each(function (EmailRecipient $recipient) use ($job, $errorSanitizer): void {
            if ($recipient->error_message !== null) {
                $recipient->setAttribute(
                    'safe_error_message',
                    $errorSanitizer->sanitize($recipient->error_message, $job->smtpConfiguration),
                );
            }
        });

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
            if (! Cache::has($lockKey)) {
                // No job is currently processing, dispatch a new one
                ProcessEmailJob::dispatch($emailJob, $emailJob->smtpConfiguration);
            }
            // If lock exists, a job is already processing, so we don't need to dispatch another
        }

        return back()->with('success', 'Job resumed successfully.');
    }

    protected function extractDomain(string $email): string
    {
        $parts = explode('@', $email);

        return strtolower($parts[1] ?? 'unknown');
    }
}
