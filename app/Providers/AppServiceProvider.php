<?php

namespace App\Providers;

use App\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure route model binding for smtp resource
        Route::bind('smtp', function ($value) {
            return SmtpConfiguration::findOrFail($value);
        });

        // Configure route model binding for jobs resource (resource uses 'job' as parameter)
        Route::bind('job', function ($value) {
            return \App\Models\EmailJob::findOrFail($value);
        });

        // Also bind emailJob for custom routes (pause/resume)
        Route::bind('emailJob', function ($value) {
            return \App\Models\EmailJob::findOrFail($value);
        });
    }
}
