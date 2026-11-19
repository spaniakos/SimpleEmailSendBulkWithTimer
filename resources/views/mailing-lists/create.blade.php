<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Mailing List') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($errors->any())
                        <div class="mb-4 rounded border border-red-400 bg-red-100 px-4 py-3 text-red-800">
                            <ul class="list-inside list-disc">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('lists.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('List name')" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required maxlength="255" />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="csv_file" :value="__('Import CSV file')" />
                            <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt" class="mt-1 block w-full">
                            <p class="mt-1 text-sm text-gray-600">The file is processed for this import and is not kept as a list attachment.</p>
                        </div>

                        <div class="mb-4">
                            <x-input-label for="csv_text" :value="__('Or paste email addresses')" />
                            <textarea id="csv_text" name="csv_text" rows="6" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="person@example.com, another@example.com">{{ old('csv_text') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('lists.index') }}" class="text-gray-600 hover:text-gray-800">Cancel</a>
                            <x-primary-button>{{ __('Create List') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
