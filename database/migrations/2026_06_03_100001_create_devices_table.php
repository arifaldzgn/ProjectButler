<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Human-readable name — user can rename (e.g. "iPhone 15 Pro")
            $table->string('name', 100);

            // Which platform this device represents
            $table->enum('platform', ['ios', 'android', 'desktop', 'web', 'raycast', 'telegram'])
                  ->default('ios');

            // The Sanctum personal_access_token backing this device.
            // Nullable because the Telegram "device" is virtual (no token).
            $table->foreignId('token_id')
                  ->nullable()
                  ->constrained('personal_access_tokens')
                  ->nullOnDelete();

            // Soft-disable without revoking (useful for temporarily blocking a device)
            $table->boolean('is_active')->default(true);

            // Set on every authenticated request through ValidateShortcutRequest
            $table->timestamp('last_used_at')->nullable();

            // Store device model, OS version, app version, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
