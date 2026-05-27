<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // bulking = gain muscle (calorie surplus target)
            // cutting = lose fat (calorie deficit target)
            // maintenance = maintain weight (at-goal calories)
            $table->enum('calorie_goal_type', ['bulking', 'cutting', 'maintenance'])
                  ->default('maintenance')
                  ->after('daily_calorie_goal')
                  ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calorie_goal_type');
        });
    }
};
