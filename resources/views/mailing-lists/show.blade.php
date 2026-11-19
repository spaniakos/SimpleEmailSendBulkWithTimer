<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $mailingList->name }}</h2>
            <a href="{{ route('lists.index') }}" class="text-blue-600 hover:text-blue-800">Back to Lists</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded border border-green-400 bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded border border-red-400 bg-red-100 px-4 py-3 text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="mb-4 text-lg font-semibold">Add contacts</h3>
                    <form method="POST" action="{{ route('lists.contacts.store', $mailingList) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label for="csv_file" class="block text-sm font-medium text-gray-700">CSV file</label>
                            <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt" class="mt-1 block w-full">
                        </div>
                        <div class="mb-4">
                            <label for="csv_text" class="block text-sm font-medium text-gray-700">Or paste email addresses</label>
                            <textarea id="csv_text" name="csv_text" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                        <x-primary-button>{{ __('Import Contacts') }}</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="mb-4 text-lg font-semibold">Contacts ({{ $mailingList->contacts->count() }})</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subscription</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($mailingList->contacts as $contact)
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $contact->email_address }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            @if($contact->unsubscribed_at)
                                                <span class="font-medium text-red-700">Unsubscribed</span>
                                            @else
                                                <span class="font-medium text-green-700">Subscribed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-6 py-4 text-sm text-gray-500">No contacts in this list.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
