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
                    <fieldset class="space-y-4 border-t pt-5">
                        <legend class="font-semibold text-gray-800">{{ __('Sending rate limits') }}</legend>
                        <p class="text-sm text-gray-600">{{ __('These rolling limits apply across your campaigns. Destination limits use the recipient email domain. Changes are read by active queue workers as they reserve each email; workers do not need to be restarted.') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="max_emails_per_window" class="block font-medium text-sm text-gray-700">{{ __('Maximum emails per window') }}</label>
                                <input id="max_emails_per_window" name="max_emails_per_window" type="number" min="1" max="100000" required value="{{ old('max_emails_per_window', $rateLimits['max_emails_per_window']) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <x-input-error :messages="$errors->get('max_emails_per_window')" class="mt-2" />
                            </div>
                            <div>
                                <label for="window_seconds" class="block font-medium text-sm text-gray-700">{{ __('Global window (seconds)') }}</label>
                                <input id="window_seconds" name="window_seconds" type="number" min="1" max="86400" required value="{{ old('window_seconds', $rateLimits['window_seconds']) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <x-input-error :messages="$errors->get('window_seconds')" class="mt-2" />
                            </div>
                            <div>
                                <label for="max_emails_per_domain" class="block font-medium text-sm text-gray-700">{{ __('Maximum emails per recipient domain') }}</label>
                                <input id="max_emails_per_domain" name="max_emails_per_domain" type="number" min="1" max="100000" required value="{{ old('max_emails_per_domain', $rateLimits['max_emails_per_domain']) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <x-input-error :messages="$errors->get('max_emails_per_domain')" class="mt-2" />
                            </div>
                            <div>
                                <label for="domain_window_seconds" class="block font-medium text-sm text-gray-700">{{ __('Recipient domain window (seconds)') }}</label>
                                <input id="domain_window_seconds" name="domain_window_seconds" type="number" min="1" max="86400" required value="{{ old('domain_window_seconds', $rateLimits['domain_window_seconds']) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <x-input-error :messages="$errors->get('domain_window_seconds')" class="mt-2" />
                            </div>
                        </div>
                    </fieldset>
                    <x-primary-button>{{ __('Save settings') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
