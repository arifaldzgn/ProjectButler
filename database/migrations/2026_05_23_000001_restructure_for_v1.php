<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure database to match Project Butler v1 spec.
     *
     * This migration:
     * 1. Modifies users table (telegram_chat_id as primary identity)
     * 2. Creates unified entries table (replaces transactions + food_logs)
     * 3. Creates streaks table
     * 4. Creates ai_logs table
     * 5. Recreates reminders table
     * 6. Recreates daily_summaries table
     * 7. Drops legacy tables
     */
    public function up(): void
    {
        // ── 1. Modify users table ──────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            // Drop old auth columns
            $table->dropColumn(['email', 'email_verified_at', 'password', 'remember_token']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Add Telegram identity
            $table->bigInteger('telegram_chat_id')->unique()->after('name');
            $table->string('telegram_username', 64)->nullable()->after('telegram_chat_id');

            // Timezone & language
            $table->string('timezone', 64)->default('Asia/Jakarta')->after('telegram_username');
            $table->enum('preferred_language', ['id', 'en'])->default('id')->after('timezone');

            // Goals (nullable — user may skip)
            $table->integer('daily_budget_idr')->nullable()->after('preferred_language');
            $table->integer('daily_calorie_goal')->nullable()->after('daily_budget_idr');

            // Onboarding state machine
            $table->enum('onboarding_step', ['new', 'asked_name', 'asked_budget', 'asked_calorie', 'complete'])
                  ->default('new')->after('daily_calorie_goal');
            $table->timestamp('onboarding_complete_at')->nullable()->after('onboarding_step');

            // Activity tracking
            $table->timestamp('last_active_at')->nullable()->after('onboarding_complete_at');
            $table->timestamp('last_summary_sent_at')->nullable()->after('last_active_at');
        });

        // ── 2. Create unified entries table ────────────────────────────────
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('type', ['expense', 'meal', 'saving'])->index();

            // Financials (expenses and savings)
            $table->integer('amount')->nullable();
            $table->char('currency', 3)->default('IDR');

            // Expense classification
            $table->string('category', 32)->nullable();
            $table->string('merchant', 128)->nullable();

            // Meal specific
            $table->string('food_item', 256)->nullable();
            $table->integer('calories')->nullable();
            $table->boolean('is_calorie_estimated')->default(true);

            // Shared
            $table->text('note')->nullable();
            $table->timestamp('entry_time');
            $table->json('metadata')->nullable();

            // AI provenance
            $table->text('ai_raw_input');
            $table->string('ai_intent', 32);
            $table->decimal('ai_confidence', 4, 3);
            $table->string('ai_prompt_version', 16)->default('v1');

            // Lifecycle
            $table->timestamp('confirmed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'entry_time']);
            $table->index(['user_id', 'confirmed_at']);
        });

        // ── 3. Create streaks table ────────────────────────────────────────
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');

            // Daily logging streak
            $table->integer('log_current')->default(0);
            $table->integer('log_longest')->default(0);
            $table->date('log_last_date')->nullable();

            // Expense-specific streak
            $table->integer('expense_current')->default(0);
            $table->integer('expense_longest')->default(0);
            $table->date('expense_last_date')->nullable();

            // Meal-specific streak
            $table->integer('meal_current')->default(0);
            $table->integer('meal_longest')->default(0);
            $table->date('meal_last_date')->nullable();

            $table->timestamp('updated_at')->nullable();
        });

        // ── 4. Create ai_logs table ────────────────────────────────────────
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('entry_id')->nullable()->constrained('entries')->onDelete('set null');

            $table->enum('call_type', ['parse', 'summary', 'clarification']);
            $table->string('prompt_version', 16);

            // Input
            $table->text('raw_input');
            $table->integer('token_count_input')->nullable();

            // Output
            $table->text('raw_output');
            $table->integer('token_count_output')->nullable();
            $table->string('intent_detected', 32)->nullable();
            $table->decimal('confidence_score', 4, 3)->nullable();

            // Performance
            $table->integer('latency_ms');
            $table->boolean('was_successful')->default(true);
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->nullable();
        });

        // ── 5. Recreate reminders table ────────────────────────────────────
        Schema::dropIfExists('reminders');

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('type', ['time_based', 'behavior_based']);

            // Time-based fields
            $table->time('trigger_time')->nullable();
            $table->string('trigger_days', 32)->default('mon,tue,wed,thu,fri,sat,sun');

            // Behavior-based fields
            $table->json('trigger_condition')->nullable();

            // Message
            $table->text('message_template');

            // State
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        // ── 6. Recreate daily_summaries table ──────────────────────────────
        Schema::dropIfExists('daily_summaries');

        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->date('summary_date');
            $table->enum('summary_type', ['daily', 'weekly'])->default('daily');

            // Snapshot of data at time of generation
            $table->integer('total_spent_idr')->default(0);
            $table->integer('total_calories')->default(0);
            $table->integer('total_saved_idr')->default(0);
            $table->integer('entry_count')->default(0);
            $table->integer('budget_remaining')->nullable();
            $table->integer('streak_at_time')->default(0);

            // AI output
            $table->text('ai_generated_text');
            $table->string('ai_prompt_version', 16)->default('v1');

            // Delivery
            $table->boolean('was_delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_error')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'summary_date', 'summary_type']);
        });

        // ── 7. Drop legacy tables ──────────────────────────────────────────
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('food_logs');
        Schema::dropIfExists('mood_logs');

        // Also drop password_reset_tokens since we no longer use password auth
        Schema::dropIfExists('password_reset_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
        Schema::dropIfExists('streaks');
        Schema::dropIfExists('entries');

        // Restore users table columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_chat_id', 'telegram_username', 'timezone',
                'preferred_language', 'daily_budget_idr', 'daily_calorie_goal',
                'onboarding_step', 'onboarding_complete_at',
                'last_active_at', 'last_summary_sent_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('password')->after('email_verified_at');
            $table->rememberToken();
        });

        // Restore legacy tables would need original migrations — not practical
        // Just note they existed
    }
};
