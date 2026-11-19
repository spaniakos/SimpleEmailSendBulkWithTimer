<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_jobs', function (Blueprint $table) {
            $table->foreignId('mailing_list_id')
                ->nullable()
                ->after('smtp_configuration_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mailing_list_id');
        });
    }
};
