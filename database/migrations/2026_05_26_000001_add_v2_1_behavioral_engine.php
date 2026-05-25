<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v2.1 — Behavioral Engine + Undo Architecture
     *
     * New:     behavioral_memory table
     * Modified: entries (undo_token, undo_expires_at, is_undone, correction_reason)
     *           users  (default_account_id → future, consent_asked_at on behavioral rows)
     */
    public function up(): void
    {
        // ── 1. Create behavioral_memory table ─────────────────────────
        Schema::create('behavioral_memory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('domain', 32); // merchant_account|food_calories|food_portion|meal_timing|category_account|spend_rhythm
            $table->string('subject', 128); // "GoFood", "Ayam Geprek", "lunch"

            $table->json('value'); // flexible per domain
            $table->json('context_json')->nullable(); // forward-compat: time-of-day rules

            // Trust
            $table->decimal('behavioral_confidence', 4, 3)->default(0.100);
            $table->integer('observation_count')->default(1);
            $table->boolean('user_consented')->default(false);
            $table->boolean('auto_apply')->default(false);
            $table->timestamp('consent_asked_at')->nullable();

            $table->timestamp('last_observed_at')->nullable();
            $table->timestamps();

            // One preference per user/domain/subject/context combination
            $table->unique(['user_id', 'domain', 'subject'], 'behavioral_memory_unique');
            $table->index(['user_id', 'domain']);
            $table->index(['user_id', 'behavioral_confidence']);
        });

        // ── 2. Add undo + correction columns to entries ────────────────
        Schema::table('entries', function (Blueprint $table) {
            $table->string('undo_token', 64)->nullable()->after('source_fund_confirmed');
            $table->timestamp('undo_expires_at')->nullable()->after('undo_token');
            $table->boolean('is_undone')->default(false)->after('undo_expires_at');

            // Correction audit trail — which field the user corrected
            $table->string('correction_reason', 32)->nullable()->after('is_undone');
            // Values: wrong_account|wrong_category|wrong_amount|wrong_food_estimate|wrong_merchant|duplicate

            // Telegram message ID of the confirmation message (needed to edit it for undo)
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('correction_reason');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn([
                'undo_token',
                'undo_expires_at',
                'is_undone',
                'correction_reason',
                'telegram_message_id',
            ]);
        });

        Schema::dropIfExists('behavioral_memory');
    }
};
