<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Echo the AI response back to Telegram after a Shortcut triggers it
            $table->boolean('shortcut_notifications')->default(true)->after('daily_summary_enabled');

            // async = fire-and-forget, returns immediately
            // sync  = waits up to N seconds for an AI response before returning
            $table->string('shortcut_mode', 20)->default('async')->after('shortcut_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['shortcut_notifications', 'shortcut_mode']);
        });
    }
};
