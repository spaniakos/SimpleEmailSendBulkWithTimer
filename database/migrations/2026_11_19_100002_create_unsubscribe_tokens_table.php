<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unsubscribe_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unsubscribe_tokens');
    }
};
