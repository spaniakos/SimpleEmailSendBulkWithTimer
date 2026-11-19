<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Email Settings') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('settings.email.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="unsubscribe_footer_text" class="block font-medium text-sm text-gray-700">{{ __('Unsubscribe footer text') }}</label>
                        <textarea id="unsubscribe_footer_text" name="unsubscribe_footer_text" maxlength="500" required rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('unsubscribe_footer_text', $footerText) }}</textarea>
                        <p class="mt-1 text-sm text-gray-600">{{ __('This text is escaped and included above the unsubscribe link in every campaign email.') }}</p>
                        <x-input-error :messages="$errors->get('unsubscribe_footer_text')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ __('Save settings') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
