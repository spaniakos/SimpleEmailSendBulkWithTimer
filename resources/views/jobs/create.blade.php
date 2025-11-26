<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Email Job') }}
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

                    <form method="POST" action="{{ route('jobs.store') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Job Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Job Name (Optional)')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" />
                        </div>

                        <!-- SMTP Configuration -->
                        <div class="mb-4">
                            <x-input-label for="smtp_configuration_id" :value="__('SMTP Configuration')" />
                            <select id="smtp_configuration_id" name="smtp_configuration_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select SMTP Configuration</option>
                                @foreach($smtpConfigurations as $config)
                                    <option value="{{ $config->id }}" {{ old('smtp_configuration_id') == $config->id ? 'selected' : '' }}>
                                        {{ $config->name ?? $config->host }} ({{ $config->from_email }})
                                    </option>
                                @endforeach
                            </select>
                            @if($smtpConfigurations->count() === 0)
                                <p class="mt-2 text-sm text-gray-600">
                                    No SMTP configurations found. <a href="{{ route('smtp.create') }}" class="text-blue-600 hover:text-blue-800">Create one first</a>
                                </p>
                            @endif
                        </div>

                        <!-- Subject -->
                        <div class="mb-4">
                            <x-input-label for="subject" :value="__('Email Subject')" />
                            <x-text-input id="subject" class="block mt-1 w-full" type="text" name="subject" :value="old('subject')" required />
                        </div>

                        <!-- HTML Content -->
                        <div class="mb-4">
                            <x-input-label for="html_content" :value="__('HTML Content')" />
                            <textarea id="html_content" name="html_content" rows="10" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('html_content') }}</textarea>
                            <p class="mt-2 text-sm text-gray-600">Enter HTML formatted email content</p>
                        </div>

                        <!-- CSV Upload -->
                        <div class="mb-4">
                            <x-input-label for="csv_file" :value="__('CSV File (Optional)')" />
                            <input id="csv_file" type="file" name="csv_file" accept=".csv,.txt" class="block mt-1 w-full">
                            <p class="mt-2 text-sm text-gray-600">Upload a CSV file with email addresses (one per line or comma-separated)</p>
                        </div>

                        <!-- OR CSV Text -->
                        <div class="mb-4">
                            <x-input-label for="csv_text" :value="__('OR Paste Email Addresses')" />
                            <textarea id="csv_text" name="csv_text" rows="5" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="email1@example.com, email2@example.com">{{ old('csv_text') }}</textarea>
                            <p class="mt-2 text-sm text-gray-600">Paste email addresses separated by commas or new lines</p>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('jobs.index') }}" class="text-gray-600 hover:text-gray-800 mr-4">Cancel</a>
                            <x-primary-button>
                                {{ __('Create Job') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

