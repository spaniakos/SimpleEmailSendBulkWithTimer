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

                        <!-- Mailing List -->
                        <div class="mb-4">
                            <x-input-label for="mailing_list_id" :value="__('Recipient List')" />
                            <select id="mailing_list_id" name="mailing_list_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select a recipient list</option>
                                @foreach($mailingLists as $mailingList)
                                    <option value="{{ $mailingList->id }}" {{ old('mailing_list_id') == $mailingList->id ? 'selected' : '' }}>
                                        {{ $mailingList->name }} ({{ $mailingList->subscribed_contacts_count }} subscribed contacts)
                                    </option>
                                @endforeach
                            </select>
                            @if($mailingLists->isEmpty())
                                <p class="mt-2 text-sm text-gray-600">
                                    Create a list and import contacts before starting a job.
                                    <a href="{{ route('lists.create') }}" class="text-blue-600 hover:text-blue-800">Create a mailing list</a>
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
                            <textarea id="html_content" name="html_content" class="mt-1 w-full">{{ old('html_content', '') }}</textarea>
                            <p class="mt-2 text-sm text-gray-600">Use the editor above to format your email content. Images and PDFs can be uploaded directly.</p>
                            <p class="mt-1 text-xs text-red-600 hidden" id="html_content_error">Please enter email content.</p>
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
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            // Initialize TinyMCE with email-compatible configuration
            tinymce.init({
                selector: '#html_content',
                height: 500,
                menubar: true,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | formatselect | ' +
                    'bold italic underline strikethrough | forecolor backcolor | ' +
                    'alignleft aligncenter alignright alignjustify | ' +
                    'bullist numlist outdent indent | removeformat | ' +
                    'link image | code fullscreen',
                content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
                // Email-compatible settings
                inline_styles: true,
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                // Image upload
                images_upload_handler: function (blobInfo, progress) {
                    return new Promise(function (resolve, reject) {
                        var xhr = new XMLHttpRequest();
                        xhr.withCredentials = false;
                        xhr.open('POST', '{{ route("upload.image") }}');
                        
                        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                        
                        xhr.upload.onprogress = function (e) {
                            progress(e.loaded / e.total * 100);
                        };
                        
                        xhr.onload = function () {
                            if (xhr.status === 403) {
                                reject({ message: 'HTTP Error: ' + xhr.status, remove: true });
                                return;
                            }
                            
                            if (xhr.status < 200 || xhr.status >= 300) {
                                reject('HTTP Error: ' + xhr.status);
                                return;
                            }
                            
                            var json = JSON.parse(xhr.responseText);
                            
                            if (!json || typeof json.location != 'string') {
                                reject('Invalid JSON: ' + xhr.responseText);
                                return;
                            }
                            
                            resolve(json.location);
                        };
                        
                        xhr.onerror = function () {
                            reject('Image upload failed');
                        };
                        
                        var formData = new FormData();
                        formData.append('file', blobInfo.blob(), blobInfo.filename());
                        
                        xhr.send(formData);
                    });
                },
                // File browser for PDFs
                file_picker_types: 'file',
                file_picker_callback: function(callback, value, meta) {
                    if (meta.filetype === 'file') {
                        var input = document.createElement('input');
                        input.setAttribute('type', 'file');
                        input.setAttribute('accept', 'application/pdf');
                        
                        input.onchange = function () {
                            var file = this.files[0];
                            var formData = new FormData();
                            formData.append('file', file);
                            
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '{{ route("upload.pdf") }}');
                            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                            
                            xhr.onload = function () {
                                if (xhr.status === 200) {
                                    var json = JSON.parse(xhr.responseText);
                                    callback(json.location, { text: file.name });
                                } else {
                                    alert('PDF upload failed');
                                }
                            };
                            
                            xhr.send(formData);
                        };
                        
                        input.click();
                    }
                },
                setup: function(editor) {
                    // Add custom button for PDF upload
                    editor.ui.registry.addButton('pdfupload', {
                        text: 'PDF',
                        tooltip: 'Upload PDF',
                        onAction: function() {
                            var input = document.createElement('input');
                            input.setAttribute('type', 'file');
                            input.setAttribute('accept', 'application/pdf');
                            
                            input.onchange = function () {
                                var file = this.files[0];
                                var formData = new FormData();
                                formData.append('file', file);
                                
                                var xhr = new XMLHttpRequest();
                                xhr.open('POST', '{{ route("upload.pdf") }}');
                                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                                
                                xhr.onload = function () {
                                    if (xhr.status === 200) {
                                        var json = JSON.parse(xhr.responseText);
                                        editor.insertContent('<a href="' + json.location + '" target="_blank">' + file.name + '</a>');
                                    } else {
                                        alert('PDF upload failed');
                                    }
                                };
                                
                                xhr.send(formData);
                            };
                            
                            input.click();
                        }
                    });
                }
            });

            // Form validation
            var form = document.querySelector('form');
            var errorMessage = document.getElementById('html_content_error');
            
            form.addEventListener('submit', function(e) {
                // Sync TinyMCE content to textarea before validation
                tinymce.triggerSave();
                
                // Get content from TinyMCE
                var htmlContent = tinymce.get('html_content').getContent();
                
                // Also get from textarea (in case TinyMCE hasn't synced yet)
                if (!htmlContent) {
                    htmlContent = document.getElementById('html_content').value;
                }
                
                // Check if content has actual text (not just HTML tags)
                var textContent = htmlContent.replace(/<[^>]*>/g, '').trim();
                
                // Ensure we have at least some content
                if (!htmlContent || htmlContent === '' || !textContent) {
                    e.preventDefault();
                    e.stopPropagation();
                    errorMessage.classList.remove('hidden');
                    errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                
                // Update textarea with content before submission
                document.getElementById('html_content').value = htmlContent;
                errorMessage.classList.add('hidden');
            });
        });
    </script>
</x-app-layout>

