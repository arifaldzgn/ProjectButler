<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // user = human input, assistant = AI/bot reply, system = internal event
            $table->enum('role', ['user', 'assistant', 'system'])->default('user');

            // The raw message content
            $table->text('content');

            // Detected or hinted intent (nullable — not all messages have intents)
            $table->string('intent', 60)->nullable();

            // AI confidence score (0.0–1.0), null for non-AI messages
            $table->decimal('confidence', 4, 3)->nullable();

            // Latency of the AI call in ms, if applicable
            $table->unsignedInteger('ai_latency_ms')->nullable();

            // Cross-reference to shortcut_messages if this came from the API
            $table->foreignId('shortcut_message_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Extra metadata: model used, token counts, device_id, etc.
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
