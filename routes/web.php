<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailJobController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\MailingListController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmtpConfigurationController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/unsubscribe/{token}', [UnsubscribeController::class, 'confirm'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:30,1')->name('unsubscribe.confirm');
Route::post('/unsubscribe/{token}', [UnsubscribeController::class, 'consume'])
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
    ->middleware('throttle:30,1')->name('unsubscribe.consume');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/settings/email', [EmailSettingsController::class, 'edit'])->name('settings.email.edit');
    Route::put('/settings/email', [EmailSettingsController::class, 'update'])->name('settings.email.update');

    // SMTP Configuration Routes
    Route::resource('smtp', SmtpConfigurationController::class);
    Route::post('/smtp/test-connection', [SmtpConfigurationController::class, 'testConnection'])->name('smtp.test-connection');

    // Email Job Routes
    Route::resource('jobs', EmailJobController::class);
    Route::post('/jobs/{emailJob}/pause', [EmailJobController::class, 'pause'])->name('jobs.pause');
    Route::post('/jobs/{emailJob}/resume', [EmailJobController::class, 'resume'])->name('jobs.resume');

    // Mailing Lists and Contacts
    Route::resource('lists', MailingListController::class)
        ->parameters(['lists' => 'mailingList'])
        ->only(['index', 'create', 'store', 'show']);
    Route::post('/lists/{mailingList}/contacts', [MailingListController::class, 'addContacts'])->name('lists.contacts.store');

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Upload Routes
    Route::post('/upload/image', [UploadController::class, 'uploadImage'])->name('upload.image');
    Route::post('/upload/pdf', [UploadController::class, 'uploadPdf'])->name('upload.pdf');
});

require __DIR__.'/auth.php';
