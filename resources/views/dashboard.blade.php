<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Total Jobs</div>
                        <div class="text-2xl font-bold text-gray-900">{{ $totalJobs }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Active Jobs</div>
                        <div class="text-2xl font-bold text-blue-600">{{ $activeJobs }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Emails Sent</div>
                        <div class="text-2xl font-bold text-green-600">{{ $sentEmails }} / {{ $totalEmails }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Failed Emails</div>
                        <div class="text-2xl font-bold text-red-600">{{ $failedEmails }}</div>
                    </div>
                </div>
            </div>

            <!-- Queue Status -->
            @if($queueJobs > 0 || $pendingRecipients > 0)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <h3 class="text-lg font-semibold text-yellow-800 mb-2">Queue Status</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Jobs in Queue:</span>
                        <span class="ml-2 font-bold {{ $readyJobs > 0 ? 'text-blue-600' : 'text-gray-600' }}">{{ $queueJobs }}</span>
                        @if($readyJobs > 0)
                            <span class="text-xs text-blue-600">({{ $readyJobs }} ready)</span>
                        @endif
                    </div>
                    @if($delayedJobs > 0)
                    <div>
                        <span class="font-medium text-gray-700">Delayed Jobs:</span>
                        <span class="ml-2 font-bold text-yellow-600">{{ $delayedJobs }}</span>
                        <span class="text-xs text-gray-500">(waiting for rate limit)</span>
                    </div>
                    @endif
                    @if($failedJobs > 0)
                    <div>
                        <span class="font-medium text-gray-700">Failed Jobs:</span>
                        <span class="ml-2 font-bold text-red-600">{{ $failedJobs }}</span>
                    </div>
                    @endif
                    @if($pendingRecipients > 0 && $queueJobs == 0)
                    <div>
                        <span class="font-medium text-gray-700">Pending Recipients:</span>
                        <span class="ml-2 font-bold text-orange-600">{{ $pendingRecipients }}</span>
                        <span class="text-xs text-gray-500">(no jobs in queue)</span>
                    </div>
                    @endif
                </div>
                @if($pendingRecipients > 0 && $queueJobs == 0)
                <div class="mt-3 text-sm text-yellow-700">
                    <strong>⚠️ Warning:</strong> You have {{ $pendingRecipients }} pending recipients but no jobs in the queue. 
                    This might indicate jobs were released due to rate limiting. Make sure <code class="bg-yellow-100 px-1 rounded">php artisan queue:work</code> is running.
                </div>
                @endif
            </div>
            @endif

            <!-- Recent Jobs -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Jobs</h3>
                        <a href="{{ route('jobs.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create New Job
                        </a>
                    </div>
                    
                    @if($recentJobs->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentJobs as $job)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="{{ route('jobs.show', $job) }}" class="text-blue-600 hover:text-blue-800">
                                                    {{ $job->name ?? 'Untitled Job' }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($job->status === 'completed') bg-green-100 text-green-800
                                                    @elseif($job->status === 'failed') bg-red-100 text-red-800
                                                    @elseif($job->status === 'processing') bg-blue-100 text-blue-800
                                                    @elseif($job->status === 'paused') bg-yellow-100 text-yellow-800
                                                    @else bg-gray-100 text-gray-800
                                                    @endif">
                                                    {{ ucfirst($job->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $job->sent_count }} / {{ $job->total_recipients }}
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $job->progress_percentage }}%"></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $job->created_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('jobs.show', $job) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">No jobs yet. <a href="{{ route('jobs.create') }}" class="text-blue-600 hover:text-blue-800">Create your first job</a></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
