<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortcut_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The raw text the user sent from their Shortcut
            $table->text('message');

            // Which client sent this (iphone_shortcut, android, web, desktop)
            $table->string('source', 50)->default('iphone_shortcut');

            // The response returned to the client (null until processed)
            $table->text('response')->nullable();

            // Processing lifecycle
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');

            // Optional device/client metadata (shortcut version, device model, etc.)
            $table->json('metadata')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortcut_messages');
    }
};
