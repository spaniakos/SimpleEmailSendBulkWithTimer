<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_settings', function (Blueprint $table) {
            $table->unsignedInteger('max_emails_per_window')->default(6);
            $table->unsignedInteger('window_seconds')->default(60);
            $table->unsignedInteger('max_emails_per_domain')->default(1);
            $table->unsignedInteger('domain_window_seconds')->default(240);
        });

        DB::table('email_settings')->update([
            'max_emails_per_window' => max(1, (int) config('email.rate_limit.max_emails_per_window', 6)),
            'window_seconds' => max(1, (int) config('email.rate_limit.window_seconds', 60)),
            'max_emails_per_domain' => max(1, (int) config('email.rate_limit.max_emails_per_domain', config('email.rate_limit.emails_per_domain', 1))),
            'domain_window_seconds' => max(1, (int) config('email.rate_limit.domain_window_seconds', config('email.rate_limit.seconds', 240))),
        ]);

        Schema::create('email_send_rate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_domain', 255);
            $table->timestamp('reserved_at');

            $table->index(['user_id', 'reserved_at']);
            $table->index(['user_id', 'recipient_domain', 'reserved_at'], 'rate_events_domain_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_rate_events');

        Schema::table('email_settings', function (Blueprint $table) {
            $table->dropColumn([
                'max_emails_per_window',
                'window_seconds',
                'max_emails_per_domain',
                'domain_window_seconds',
            ]);
        });
    }
};
