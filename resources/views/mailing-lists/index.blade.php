<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Mailing Lists') }}</h2>
            <a href="{{ route('lists.create') }}" class="rounded bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Create List</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded border border-green-400 bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($mailingLists->isEmpty())
                        <p class="text-gray-600">No mailing lists yet. Create a list to import and reuse contacts.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">List</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contacts</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subscribed</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($mailingLists as $mailingList)
                                        <tr>
                                            <td class="px-6 py-4 text-sm font-medium">
                                                <a class="text-blue-600 hover:text-blue-800" href="{{ route('lists.show', $mailingList) }}">{{ $mailingList->name }}</a>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-700">{{ $mailingList->contacts_count }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-700">{{ $mailingList->subscribed_contacts_count }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">{{ $mailingList->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
