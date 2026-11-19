<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_recipients', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->timestamp('suppressed_at')->nullable()->after('sent_at');
        });

        Schema::table('email_jobs', function (Blueprint $table) {
            $table->unsignedInteger('suppressed_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('email_jobs', function (Blueprint $table) {
            $table->dropColumn('suppressed_count');
        });

        Schema::table('email_recipients', function (Blueprint $table) {
            $table->dropColumn('suppressed_at');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->change();
        });
    }
};
