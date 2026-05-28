<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Per-bill reminder time (e.g. "09:00"). Null = use system default (09:00).
            $table->time('reminder_time')->nullable()->default(null)->after('due_day');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('reminder_time');
        });
    }
};
