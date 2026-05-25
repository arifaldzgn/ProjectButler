<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Webview Integration — Canonical users table sync.
     *
     * Adds all columns required by project-butler-integration-guide.md:
     * - default_account_id  → references funds(id) (our spending account)
     * - currency            → already exists, ensure it's there
     * - monthly_budget_idr  → renamed from daily_budget_idr scope; add monthly
     * - health_goal         → ENUM for health tracking goal
     * - daily_summary_enabled / summary_time → for notification scheduler
     * - onboarding_started_at → when webview was first opened
     *
     * Also normalises onboarding_step to the unified state machine values.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default account — references funds table (our spending accounts)
            if (!Schema::hasColumn('users', 'default_account_id')) {
                $table->unsignedBigInteger('default_account_id')
                      ->nullable()
                      ->after('has_income_set');
                $table->foreign('default_account_id')
                      ->references('id')->on('funds')
                      ->onDelete('set null');
            }

            // Monthly budget (the integration guide uses monthly_budget_idr)
            if (!Schema::hasColumn('users', 'monthly_budget_idr')) {
                $table->integer('monthly_budget_idr')->nullable()->after('monthly_income_idr');
            }

            // Currency field
            if (!Schema::hasColumn('users', 'currency')) {
                $table->char('currency', 3)->default('IDR')->after('name');
            }

            // Health goal
            if (!Schema::hasColumn('users', 'health_goal')) {
                $table->enum('health_goal', ['maintain', 'lose', 'gain', 'protein'])
                      ->nullable()
                      ->after('daily_calorie_goal');
            }

            // Daily summary enabled flag
            if (!Schema::hasColumn('users', 'daily_summary_enabled')) {
                $table->boolean('daily_summary_enabled')->default(true)->after('health_goal');
            }

            // Summary time (stored in user's own timezone)
            if (!Schema::hasColumn('users', 'summary_time')) {
                $table->time('summary_time')->default('21:00:00')->after('daily_summary_enabled');
            }

            // When the webview was first opened
            if (!Schema::hasColumn('users', 'onboarding_started_at')) {
                $table->timestamp('onboarding_started_at')->nullable()->after('onboarding_complete_at');
            }
        });

        // Normalize onboarding_step to unified state machine values
        DB::statement("UPDATE `users` SET `onboarding_step` = 'new'
                       WHERE `onboarding_step` IN ('mode_select', 'pending', 'asked_name', 'asked_budget', 'asked_calorie')
                         AND `onboarding_complete_at` IS NULL");

        DB::statement("UPDATE `users` SET `onboarding_step` = 'complete'
                       WHERE `onboarding_complete_at` IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_account_id']);
            $table->dropColumn([
                'default_account_id',
                'monthly_budget_idr',
                'health_goal',
                'daily_summary_enabled',
                'summary_time',
                'onboarding_started_at',
            ]);

            // Drop currency only if we added it
            if (Schema::hasColumn('users', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
