<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streaks', function (Blueprint $table) {
            // Budget streak: consecutive days the user stayed under daily budget
            $table->unsignedInteger('budget_current')->default(0)->after('meal_last_date');
            $table->unsignedInteger('budget_longest')->default(0)->after('budget_current');
            $table->date('budget_last_date')->nullable()->after('budget_longest');

            // Calorie streak: consecutive days the user hit their calorie goal
            $table->unsignedInteger('calorie_current')->default(0)->after('budget_last_date');
            $table->unsignedInteger('calorie_longest')->default(0)->after('calorie_current');
            $table->date('calorie_last_date')->nullable()->after('calorie_longest');
        });
    }

    public function down(): void
    {
        Schema::table('streaks', function (Blueprint $table) {
            $table->dropColumn([
                'budget_current', 'budget_longest', 'budget_last_date',
                'calorie_current', 'calorie_longest', 'calorie_last_date',
            ]);
        });
    }
};
