<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Job Details') }}: {{ $emailJob->name ?? 'Untitled Job' }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('jobs.edit', $emailJob) }}" class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Edit
                </a>
                @if($emailJob->status === 'paused')
                    <form action="{{ route('jobs.resume', $emailJob) }}" method="POST" class="inline">
                        @csrf
                        <x-primary-button type="submit">Resume</x-primary-button>
                    </form>
                @elseif(in_array($emailJob->status, ['pending', 'processing']))
                    <form action="{{ route('jobs.pause', $emailJob) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-gray-900 uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Pause
                        </button>
                    </form>
                @endif
                <a href="{{ route('jobs.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Jobs
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Email Preview -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Email Preview</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 mb-1">Subject</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $emailJob->subject }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 mb-2">Email Content</dt>
                            <dd class="border border-gray-300 rounded-md p-4 bg-gray-50">
                                <div class="email-preview">
                                    {!! $emailJob->html_content !!}
                                </div>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Job Info -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Job Information</h3>
                            <dl class="space-y-2">
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($emailJob->status === 'completed') bg-green-100 text-green-800
                                        @elseif($emailJob->status === 'failed') bg-red-100 text-red-800
                                        @elseif($emailJob->status === 'processing') bg-blue-100 text-blue-800
                                        @elseif($emailJob->status === 'paused') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($emailJob->status) }}
                                    </span>
                                </dd>
                                
                                <dt class="text-sm font-medium text-gray-500">SMTP Configuration</dt>
                                <dd class="text-sm text-gray-900">{{ $emailJob->smtpConfiguration->name ?? $emailJob->smtpConfiguration->host }}</dd>
                                
                                <dt class="text-sm font-medium text-gray-500">Created</dt>
                                <dd class="text-sm text-gray-900">{{ $emailJob->created_at->format('M d, Y H:i:s') }}</dd>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Statistics</h3>
                            <dl class="space-y-2">
                                <dt class="text-sm font-medium text-gray-500">Total Recipients</dt>
                                <dd class="text-2xl font-bold text-gray-900">{{ $emailJob->total_recipients }}</dd>
                                
                                <dt class="text-sm font-medium text-gray-500">Sent</dt>
                                <dd class="text-2xl font-bold text-green-600">{{ $emailJob->sent_count }}</dd>
                                
                                <dt class="text-sm font-medium text-gray-500">Failed</dt>
                                <dd class="text-2xl font-bold text-red-600">{{ $emailJob->failed_count }}</dd>
                                
                                <dt class="text-sm font-medium text-gray-500">Progress</dt>
                                <dd>
                                    <div class="text-2xl font-bold text-blue-600">{{ $emailJob->progress_percentage }}%</div>
                                    <div class="w-full bg-gray-200 rounded-full h-4 mt-2">
                                        <div class="bg-blue-600 h-4 rounded-full" style="width: {{ $emailJob->progress_percentage }}%"></div>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipients List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Recipients</h3>
                    
                    <div class="mb-4 flex gap-2">
                        <a href="?status=all" class="px-3 py-1 rounded {{ request('status') === 'all' || !request('status') ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' }}">All</a>
                        <a href="?status=sent" class="px-3 py-1 rounded {{ request('status') === 'sent' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700' }}">Sent</a>
                        <a href="?status=failed" class="px-3 py-1 rounded {{ request('status') === 'failed' ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-700' }}">Failed</a>
                        <a href="?status=pending" class="px-3 py-1 rounded {{ request('status') === 'pending' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-200 text-gray-700' }}">Pending</a>
                    </div>

                    @php
                        $filteredRecipients = $emailJob->recipients;
                        if(request('status') && request('status') !== 'all') {
                            $filteredRecipients = $filteredRecipients->where('status', request('status'));
                        }
                    @endphp

                    @if($filteredRecipients->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($filteredRecipients as $recipient)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $recipient->email_address }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $recipient->domain }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($recipient->status === 'sent') bg-green-100 text-green-800
                                                    @elseif($recipient->status === 'failed') bg-red-100 text-red-800
                                                    @else bg-yellow-100 text-yellow-800
                                                    @endif">
                                                    {{ ucfirst($recipient->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $recipient->sent_at ? $recipient->sent_at->format('M d, Y H:i:s') : '-' }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-red-600">
                                                {{ $recipient->error_message ? Str::limit($recipient->error_message, 50) : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">No recipients found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(in_array($emailJob->status, ['pending', 'processing']))
        <script>
            // Auto-refresh at rate limit + 1 second for active jobs
            @php
                $rateLimitSeconds = config('email.rate_limit.seconds', 10);
                $refreshInterval = ($rateLimitSeconds + 1) * 1000; // Convert to milliseconds
            @endphp
            setTimeout(function() {
                location.reload();
            }, {{ $refreshInterval }});
        </script>
    @endif
</x-app-layout>

