<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add SMTP Configuration') }}
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

                    <form method="POST" action="{{ route('smtp.store') }}">
                        @csrf

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Configuration Name (Optional)')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g., Gmail SMTP" />
                        </div>

                        <!-- Host -->
                        <div class="mb-4">
                            <x-input-label for="host" :value="__('SMTP Host')" />
                            <x-text-input id="host" class="block mt-1 w-full" type="text" name="host" :value="old('host')" required placeholder="smtp.gmail.com" />
                        </div>

                        <!-- Port -->
                        <div class="mb-4">
                            <x-input-label for="port" :value="__('SMTP Port')" />
                            <x-text-input id="port" class="block mt-1 w-full" type="number" name="port" :value="old('port', 587)" required />
                            <p class="mt-2 text-sm text-gray-600">Common ports: 587 (TLS), 465 (SSL), 25</p>
                        </div>

                        <!-- Encryption -->
                        <div class="mb-4">
                            <x-input-label for="encryption" :value="__('Encryption')" />
                            <select id="encryption" name="encryption" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="tls" {{ old('encryption', 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ old('encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            </select>
                        </div>

                        <!-- Username -->
                        <div class="mb-4">
                            <x-input-label for="username" :value="__('SMTP Username')" />
                            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required />
                        </div>

                        <!-- Password -->
                        <div class="mb-4">
                            <x-input-label for="password" :value="__('SMTP Password')" />
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required />
                            <p class="mt-2 text-sm text-gray-600">Password will be encrypted and stored securely</p>
                        </div>

                        <!-- From Email -->
                        <div class="mb-4">
                            <x-input-label for="from_email" :value="__('From Email Address')" />
                            <x-text-input id="from_email" class="block mt-1 w-full" type="email" name="from_email" :value="old('from_email')" required />
                        </div>

                        <!-- From Name -->
                        <div class="mb-4">
                            <x-input-label for="from_name" :value="__('From Name (Optional)')" />
                            <x-text-input id="from_name" class="block mt-1 w-full" type="text" name="from_name" :value="old('from_name')" />
                        </div>

                        <!-- Test Connection Result -->
                        <div id="test-result" class="mb-4 hidden"></div>

                        <div class="flex items-center justify-between mt-4">
                            <button type="button" id="test-connection-btn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Test Connection
                            </button>
                            <div>
                                <a href="{{ route('smtp.index') }}" class="text-gray-600 hover:text-gray-800 mr-4">Cancel</a>
                                <x-primary-button>
                                    {{ __('Create Configuration') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('test-connection-btn').addEventListener('click', function() {
            const btn = this;
            const resultDiv = document.getElementById('test-result');
            const originalText = btn.textContent;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.textContent = 'Testing...';
            resultDiv.classList.add('hidden');
            
            // Get form values
            const formData = {
                host: document.getElementById('host').value,
                port: document.getElementById('port').value,
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
                encryption: document.getElementById('encryption').value,
                from_email: document.getElementById('from_email').value,
                _token: '{{ csrf_token() }}'
            };
            
            // Validate required fields
            if (!formData.host || !formData.port || !formData.username || !formData.password || !formData.from_email) {
                resultDiv.className = 'mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded';
                resultDiv.textContent = 'Please fill in all required fields before testing.';
                resultDiv.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = originalText;
                return;
            }
            
            // Send AJAX request
            fetch('{{ route('smtp.test-connection') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded';
                    resultDiv.textContent = data.message;
                } else {
                    resultDiv.className = 'mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded';
                    resultDiv.textContent = data.message;
                }
                resultDiv.classList.remove('hidden');
            })
            .catch(error => {
                resultDiv.className = 'mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded';
                resultDiv.textContent = 'An error occurred: ' + error.message;
                resultDiv.classList.remove('hidden');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
    </script>
</x-app-layout>

