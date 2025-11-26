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
                            <!-- Hidden textarea for form submission -->
                            <textarea id="html_content" name="html_content" class="hidden" required>{{ old('html_content', '<p><br></p>') }}</textarea>
                            <!-- Quill editor container -->
                            <div id="quill-editor" class="mt-1 bg-white" style="min-height: 300px;"></div>
                            <!-- Source code textarea (hidden by default) -->
                            <textarea id="html_source" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 hidden" rows="15" style="font-family: monospace; font-size: 14px;"></textarea>
                            <p class="mt-2 text-sm text-gray-600">Use the editor above to format your email content</p>
                            <p class="mt-1 text-xs text-red-600 hidden" id="html_content_error">Please enter email content.</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var quillEditor = document.getElementById('quill-editor');
            var sourceTextarea = document.getElementById('html_source');
            var isSourceMode = false;

            // Initialize Quill editor with full toolbar
            var quill = new Quill('#quill-editor', {
                theme: 'snow',
                modules: {
                    toolbar: {
                        container: [
                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                            [{ 'font': [] }],
                            [{ 'size': ['small', false, 'large', 'huge'] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'script': 'sub'}, { 'script': 'super' }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            [{ 'direction': 'rtl' }],
                            [{ 'align': [] }],
                            ['blockquote', 'code-block'],
                            ['link', 'image', 'video'],
                            ['clean']
                        ]
                    }
                }
            });

            // Add custom source button to toolbar
            var toolbar = quill.getModule('toolbar');
            var toolbarElement = document.querySelector('.ql-toolbar');
            if (toolbarElement) {
                var sourceButton = document.createElement('button');
                sourceButton.type = 'button';
                sourceButton.className = 'ql-source';
                sourceButton.innerHTML = '<svg viewBox="0 0 18 18"><path d="M13 1L1 13l4 4 12-12-4-4zm-1 5l-1-1-4 4-1-1 4-4-1-1-5 5v1l5 5h1l5-5V5l-5-5z"/></svg>';
                sourceButton.setAttribute('title', 'Source Code');
                sourceButton.addEventListener('click', function() {
                    toggleSourceMode();
                });
                toolbarElement.appendChild(sourceButton);
            }

            // Set initial content if exists
            var initialContent = document.getElementById('html_content').value;
            if (initialContent) {
                quill.root.innerHTML = initialContent;
                sourceTextarea.value = initialContent;
            } else {
                // Initialize with empty paragraph to ensure content exists
                quill.setText('');
            }

            function toggleSourceMode() {
                isSourceMode = !isSourceMode;
                if (isSourceMode) {
                    // Switch to source mode
                    sourceTextarea.value = quill.root.innerHTML;
                    quillEditor.style.display = 'none';
                    sourceTextarea.classList.remove('hidden');
                } else {
                    // Switch to visual mode
                    quill.root.innerHTML = sourceTextarea.value || '<p><br></p>';
                    sourceTextarea.classList.add('hidden');
                    quillEditor.style.display = 'block';
                }
            }

            // Update hidden textarea before form submission
            var form = document.querySelector('form');
            var errorMessage = document.getElementById('html_content_error');
            
            form.addEventListener('submit', function(e) {
                var htmlContent = isSourceMode ? sourceTextarea.value : quill.root.innerHTML;
                
                // Remove empty paragraph tags that Quill adds
                htmlContent = htmlContent.replace(/<p><br><\/p>/g, '').trim();
                htmlContent = htmlContent.replace(/<p><\/p>/g, '').trim();
                
                // Check if content has actual text (not just HTML tags)
                var textContent = htmlContent.replace(/<[^>]*>/g, '').trim();
                
                // Ensure we have at least some content
                if (!htmlContent || htmlContent === '' || !textContent) {
                    e.preventDefault();
                    errorMessage.classList.remove('hidden');
                    // Scroll to error
                    errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                
                errorMessage.classList.add('hidden');
                document.getElementById('html_content').value = htmlContent;
            });
            
            // Also update on content change to keep in sync
            quill.on('text-change', function() {
                if (!isSourceMode) {
                    var content = quill.root.innerHTML;
                    // Remove empty paragraph tags
                    content = content.replace(/<p><br><\/p>/g, '').trim();
                    document.getElementById('html_content').value = content || '<p><br></p>';
                }
            });
            
            // Update when source textarea changes
            sourceTextarea.addEventListener('input', function() {
                if (isSourceMode) {
                    document.getElementById('html_content').value = sourceTextarea.value;
                }
            });
        });
    </script>
</x-app-layout>

