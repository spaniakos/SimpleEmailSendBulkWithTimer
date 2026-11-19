<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\MailingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MailingListController extends Controller
{
    public function index(): View
    {
        $mailingLists = Auth::user()->mailingLists()
            ->withCount('contacts')
            ->withCount(['contacts as subscribed_contacts_count' => fn ($query) => $query->whereNull('unsubscribed_at')])
            ->latest()
            ->get();

        return view('mailing-lists.index', compact('mailingLists'));
    }

    public function create(): View
    {
        return view('mailing-lists.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('mailing_lists', 'name')->where('user_id', Auth::id())],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'csv_text' => ['nullable', 'string', 'max:10485760'],
        ]);

        if (! $request->hasFile('csv_file') && ! $request->filled('csv_text')) {
            return back()->withErrors(['csv_text' => 'Add at least one email address to the list.'])->withInput();
        }

        $mailingList = DB::transaction(function () use ($request, $validated): MailingList {
            $mailingList = Auth::user()->mailingLists()->create([
                'name' => $validated['name'],
            ]);

            $this->importContacts($mailingList, $request);

            return $mailingList;
        });

        return redirect()->route('lists.index')
            ->with('success', "List '{$mailingList->name}' created successfully.");
    }

    public function show(MailingList $mailingList): View
    {
        $mailingList = Auth::user()->mailingLists()
            ->with(['contacts' => fn ($query) => $query->orderBy('email_address')])
            ->findOrFail($mailingList->id);

        return view('mailing-lists.show', compact('mailingList'));
    }

    public function addContacts(Request $request, MailingList $mailingList): RedirectResponse
    {
        $mailingList = Auth::user()->mailingLists()->findOrFail($mailingList->id);
        $request->validate([
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'csv_text' => ['nullable', 'string', 'max:10485760'],
        ]);

        if (! $request->hasFile('csv_file') && ! $request->filled('csv_text')) {
            return back()->withErrors(['csv_text' => 'Add at least one email address.']);
        }

        $count = DB::transaction(fn (): int => $this->importContacts($mailingList, $request));

        return redirect()->route('lists.show', $mailingList)
            ->with('success', "Imported {$count} new list memberships.");
    }

    private function importContacts(MailingList $mailingList, Request $request): int
    {
        $content = $request->hasFile('csv_file')
            ? file_get_contents($request->file('csv_file')->getRealPath())
            : (string) $request->input('csv_text', '');

        $emails = $this->parseEmails($content);
        if ($emails === []) {
            throw ValidationException::withMessages([
                'csv_text' => 'The import contains no valid email addresses.',
            ]);
        }

        $newMemberships = 0;
        foreach ($emails as $email) {
            $contact = Contact::firstOrCreate([
                'user_id' => $mailingList->user_id,
                'email_address' => $email,
            ]);

            if (! $mailingList->contacts()->where('contacts.id', $contact->id)->exists()) {
                $mailingList->contacts()->attach($contact->id);
                $newMemberships++;
            }
        }

        return $newMemberships;
    }

    private function parseEmails(string $content): array
    {
        $emails = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $delimiter = str_contains($line, ';') && ! str_contains($line, ',') ? ';' : ',';
            foreach (str_getcsv($line, $delimiter) as $candidate) {
                $email = strtolower(trim($candidate));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[$email] = $email;
                }
            }
        }

        return array_values($emails);
    }
}
