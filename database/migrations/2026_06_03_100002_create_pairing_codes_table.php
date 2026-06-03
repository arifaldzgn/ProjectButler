<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 6-character alphanumeric code shown to the user (e.g. "A4K9WZ")
            $table->string('code', 10)->unique();

            // Optional device name pre-filled by the user when requesting pairing
            $table->string('device_name', 100)->nullable();

            // Platform hint so the backend can set Device.platform correctly
            $table->string('platform', 20)->default('ios');

            // When the code expires (short-lived: 10–15 minutes)
            $table->timestamp('expires_at');

            // Set when a Shortcut/client claims the code
            $table->timestamp('claimed_at')->nullable();

            // The device record created when claimed
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['code', 'expires_at']);
            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairing_codes');
    }
};
