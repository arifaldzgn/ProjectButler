<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which channel originated this conversation
            $table->enum('channel', ['telegram', 'shortcut', 'web', 'android', 'desktop'])
                  ->default('telegram');

            // Channel-specific ID: Telegram chat_id, device_id, session_id, etc.
            $table->string('channel_id', 100)->nullable();

            // Optional user-visible title (auto-generated or user-named)
            $table->string('title', 200)->nullable();

            $table->enum('status', ['active', 'archived'])->default('active');

            // Arbitrary metadata (e.g. Telegram thread_id, web session context)
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'channel', 'status']);
            $table->index(['user_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
