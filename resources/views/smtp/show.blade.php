<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('SMTP Configuration Details') }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('smtp.edit', $smtpConfiguration) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Edit
                </a>
                <a href="{{ route('smtp.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Configuration Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->name ?? 'Unnamed' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Host</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->host }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Port</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->port }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Encryption</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ strtoupper($smtpConfiguration->encryption) }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Username</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->username }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">From Email</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->from_email }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">From Name</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->from_name ?? 'N/A' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->created_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ $smtpConfiguration->updated_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                    </dl>
                    
                    <div class="mt-6 flex gap-2">
                        <a href="{{ route('smtp.edit', $smtpConfiguration) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Edit Configuration
                        </a>
                        <form action="{{ route('smtp.destroy', $smtpConfiguration) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Delete Configuration
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

