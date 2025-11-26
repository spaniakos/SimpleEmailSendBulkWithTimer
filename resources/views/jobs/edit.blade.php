<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Email Job') }}: {{ $job->name ?? 'Untitled Job' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('jobs.update', $job) }}">
                        @csrf
                        @method('PUT')

                        <!-- Job Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Job Name (Optional)')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $job->name)" />
                        </div>

                        <!-- SMTP Configuration -->
                        <div class="mb-4">
                            <x-input-label for="smtp_configuration_id" :value="__('SMTP Configuration')" />
                            <select id="smtp_configuration_id" name="smtp_configuration_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select SMTP Configuration</option>
                                @foreach($smtpConfigurations as $config)
                                    <option value="{{ $config->id }}" {{ old('smtp_configuration_id', $job->smtp_configuration_id) == $config->id ? 'selected' : '' }}>
                                        {{ $config->name ?? $config->host }} ({{ $config->from_email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="mb-4">
                            <x-input-label for="subject" :value="__('Email Subject')" />
                            <x-text-input id="subject" class="block mt-1 w-full" type="text" name="subject" :value="old('subject', $job->subject)" required />
                        </div>

                        <!-- HTML Content -->
                        <div class="mb-4">
                            <x-input-label for="html_content" :value="__('HTML Content')" />
                            <textarea id="html_content" name="html_content" rows="10" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('html_content', $job->html_content) }}</textarea>
                            <p class="mt-2 text-sm text-gray-600">Enter HTML formatted email content</p>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('jobs.show', $job) }}" class="text-gray-600 hover:text-gray-800 mr-4">Cancel</a>
                            <x-primary-button>
                                {{ __('Update Job') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

