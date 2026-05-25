<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * v1.2 — Financial Structure Expansion
     *
     * Purely additive — no tables dropped.
     * Adds: funds, fund_transactions, bills, debts
     * Modifies: users, entries, reminders, daily_summaries
     */
    public function up(): void
    {
        // ── 1. Modify users table ──────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            // Tracking mode — which features user has activated
            $table->enum('tracking_mode', ['finance', 'calorie', 'both'])
                  ->default('both')->after('preferred_language');

            // Income
            $table->integer('monthly_income_idr')->nullable()->after('tracking_mode');

            // Context storage for mid-onboarding state
            $table->json('onboarding_context')->nullable()->after('onboarding_complete_at');

            // Setup completeness flags (drive setup-incomplete reminders)
            $table->boolean('has_emergency_fund')->default(false)->after('onboarding_context');
            $table->boolean('has_bills_setup')->default(false)->after('has_emergency_fund');
            $table->boolean('has_income_set')->default(false)->after('has_bills_setup');
            $table->boolean('has_debt_declared')->default(false)->after('has_income_set');
        });

        // ── 1b. Convert onboarding_step from ENUM → VARCHAR ───────────
        // v1.1 defined it as ENUM(['new','asked_name','asked_budget','asked_calorie','complete'])
        // v1.2 adds dynamic steps like 'mode_select', 'asked_income', 'fund_detail_emergency', etc.
        DB::statement("ALTER TABLE `users` CHANGE `onboarding_step` `onboarding_step` VARCHAR(64) NOT NULL DEFAULT 'mode_select'");

        // Update existing 'new' and null values to 'mode_select' so they re-enter onboarding correctly
        DB::statement("UPDATE `users` SET `onboarding_step` = 'mode_select' WHERE `onboarding_step` IN ('new', 'asked_budget', 'asked_calorie')");
        DB::statement("UPDATE `users` SET `onboarding_step` = 'asked_name' WHERE `onboarding_step` = 'asked_name'");

        // ── 1c. Expand entries.type ENUM → VARCHAR ────────────────────
        // v1.1: ['expense', 'meal', 'saving']
        // v1.2 adds: 'income', 'bill_payment', 'sinking_fund_deposit', 'goal_deposit', 'debt_payment'
        DB::statement("ALTER TABLE `entries` CHANGE `type` `type` VARCHAR(32) NOT NULL");

        // ── 1d. Expand reminders.type ENUM → VARCHAR ──────────────────
        // v1.1: ['time_based', 'behavior_based']
        // v1.2 adds: 'setup_incomplete', 'bill_due', 'debt_due'
        DB::statement("ALTER TABLE `reminders` CHANGE `type` `type` VARCHAR(32) NOT NULL");


        // ── 2. Create funds table ──────────────────────────────────────
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('fund_type', [
                'savings',
                'sinking_fund',
                'financial_goal',
                'emergency_fund',
                'spending_budget',
            ])->index();

            $table->string('name', 128);
            $table->text('description')->nullable();

            // Balances
            $table->integer('current_balance')->default(0);
            $table->integer('initial_balance')->default(0);

            // Goal tracking (for sinking_fund and financial_goal)
            $table->integer('target_amount')->nullable();
            $table->date('target_date')->nullable();
            $table->integer('monthly_contribution')->nullable();

            // Computed helpers (updated by observer after each transaction)
            $table->decimal('progress_pct', 5, 2)->default(0.00);
            $table->integer('months_remaining')->nullable();
            $table->boolean('on_track')->nullable();

            // Flags
            $table->boolean('is_default_spending')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'fund_type']);
            $table->index(['user_id', 'is_active']);
        });

        // ── 3. Create fund_transactions table ─────────────────────────
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('entry_id')->nullable()->constrained('entries')->onDelete('set null');

            $table->enum('transaction_type', ['deposit', 'withdrawal', 'adjustment']);
            $table->integer('amount'); // always positive
            $table->text('note')->nullable();

            // Audit snapshots
            $table->integer('balance_before');
            $table->integer('balance_after');

            $table->timestamp('created_at')->nullable();

            $table->index(['fund_id', 'created_at']);
            $table->index('user_id');
        });

        // ── 4. Create bills table ──────────────────────────────────────
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('name', 128);
            $table->integer('amount');
            $table->char('currency', 3)->default('IDR');
            $table->string('category', 32)->default('bills');

            $table->tinyInteger('due_day');           // day of month 1-31
            $table->tinyInteger('due_day_flexibility')->default(0);

            // Source fund (null = free balance)
            $table->foreignId('source_fund_id')->nullable()->constrained('funds')->onDelete('set null');

            // Reminder settings
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_remind')->default(true);
            $table->tinyInteger('remind_days_before')->default(3);

            // Payment tracking (reset monthly)
            $table->timestamp('last_paid_at')->nullable();
            $table->integer('last_paid_amount')->nullable();
            $table->boolean('this_month_paid')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'due_day']);
        });

        // ── 5. Create debts table ──────────────────────────────────────
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('name', 128);
            $table->enum('debt_type', [
                'installment',
                'credit_card',
                'personal_loan',
                'mortgage',
                'paylater',
                'other',
            ]);

            // Amounts
            $table->integer('total_amount');
            $table->integer('monthly_installment');
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->integer('remaining_balance');

            // Schedule
            $table->date('start_date')->nullable();
            $table->tinyInteger('due_day');
            $table->date('end_date')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_remind')->default(true);
            $table->tinyInteger('remind_days_before')->default(3);
            $table->timestamp('last_paid_at')->nullable();
            $table->boolean('this_month_paid')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'due_day']);
        });

        // ── 6. Modify entries table — add source_fund + expand type ───
        // SQLite doesn't support modifying enums, so we use a string column
        // for new fields and handle type expansion via CHECK constraints
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('source_fund_id')
                  ->nullable()
                  ->after('merchant')
                  ->constrained('funds')
                  ->onDelete('set null');

            $table->boolean('source_fund_confirmed')->default(true)->after('source_fund_id');
        });

        // ── 7. Modify reminders table ─────────────────────────────────
        Schema::table('reminders', function (Blueprint $table) {
            // New linked references
            $table->foreignId('linked_bill_id')
                  ->nullable()
                  ->after('message_template')
                  ->constrained('bills')
                  ->onDelete('set null');

            $table->foreignId('linked_debt_id')
                  ->nullable()
                  ->after('linked_bill_id')
                  ->constrained('debts')
                  ->onDelete('set null');

            // System-created reminder flag
            $table->boolean('is_system')->default(false)->after('is_active');
        });

        // ── 8. Modify daily_summaries table ───────────────────────────
        Schema::table('daily_summaries', function (Blueprint $table) {
            // Finance
            $table->integer('total_income_idr')->default(0)->after('total_spent_idr');
            $table->integer('total_bills_paid')->default(0)->after('total_income_idr');
            $table->integer('free_balance_eod')->nullable()->after('budget_remaining');

            // Calorie enrichment
            $table->integer('calorie_goal')->nullable()->after('total_calories');
            $table->enum('calorie_status', ['under', 'on_track', 'over'])->nullable()->after('calorie_goal');

            // Funds snapshot
            $table->json('funds_snapshot')->nullable()->after('calorie_status');

            // Upcoming bills & debts
            $table->json('bills_due_this_week')->nullable()->after('funds_snapshot');
            $table->json('debts_due_this_week')->nullable()->after('bills_due_this_week');
        });
    }

    public function down(): void
    {
        // Remove added columns in reverse
        Schema::table('daily_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'total_income_idr', 'total_bills_paid', 'free_balance_eod',
                'calorie_goal', 'calorie_status',
                'funds_snapshot', 'bills_due_this_week', 'debts_due_this_week',
            ]);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['linked_bill_id']);
            $table->dropForeign(['linked_debt_id']);
            $table->dropColumn(['linked_bill_id', 'linked_debt_id', 'is_system']);
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign(['source_fund_id']);
            $table->dropColumn(['source_fund_id', 'source_fund_confirmed']);
        });

        Schema::dropIfExists('debts');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('fund_transactions');
        Schema::dropIfExists('funds');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_mode', 'monthly_income_idr', 'onboarding_context',
                'has_emergency_fund', 'has_bills_setup', 'has_income_set', 'has_debt_declared',
            ]);
        });

        // Restore ENUM columns
        DB::statement("ALTER TABLE `users` CHANGE `onboarding_step` `onboarding_step` ENUM('new','asked_name','asked_budget','asked_calorie','complete') NOT NULL DEFAULT 'new'");
        DB::statement("UPDATE `users` SET `onboarding_step` = 'new' WHERE `onboarding_step` NOT IN ('new','asked_name','asked_budget','asked_calorie','complete')");
        DB::statement("ALTER TABLE `entries` CHANGE `type` `type` ENUM('expense','meal','saving') NOT NULL");
        DB::statement("ALTER TABLE `reminders` CHANGE `type` `type` ENUM('time_based','behavior_based') NOT NULL");
    }
};
