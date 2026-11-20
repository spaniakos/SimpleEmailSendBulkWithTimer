<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_recipients', function (Blueprint $table): void {
            $table->string('message_id')->nullable()->unique();
            $table->string('delivery_status')->default('pending')->index();
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->timestamp('delivery_suppressed_at')->nullable()->index();
            $table->string('delivery_suppression_reason')->nullable();
        });

        Schema::create('email_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_recipient_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('report_id', 64)->unique();
            $table->string('diagnostic', 1000)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['email_recipient_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_events');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex(['delivery_suppressed_at']);
            $table->dropColumn(['delivery_suppressed_at', 'delivery_suppression_reason']);
        });

        Schema::table('email_recipients', function (Blueprint $table): void {
            $table->dropUnique(['message_id']);
            $table->dropIndex(['delivery_status']);
            $table->dropColumn(['message_id', 'delivery_status']);
        });
    }
};
