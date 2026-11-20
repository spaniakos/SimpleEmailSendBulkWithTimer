<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_jobs', function (Blueprint $table): void {
            $table->boolean('track_opens')->default(false);
        });

        Schema::table('email_recipients', function (Blueprint $table): void {
            $table->char('open_tracking_token_hash', 64)->nullable()->unique();
            $table->timestamp('opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_recipients', function (Blueprint $table): void {
            $table->dropUnique(['open_tracking_token_hash']);
            $table->dropColumn(['open_tracking_token_hash', 'opened_at']);
        });

        Schema::table('email_jobs', function (Blueprint $table): void {
            $table->dropColumn('track_opens');
        });
    }
};
