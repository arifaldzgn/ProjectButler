<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // YYYY-MM-DD — one row per user per channel per day
            $table->date('date');

            $table->enum('channel', ['telegram', 'shortcut', 'web', 'android', 'desktop'])
                  ->default('telegram');

            // Total requests processed this day/channel
            $table->unsignedInteger('requests_count')->default(0);

            // JSON map of intent → count, e.g. {"log_expense":5,"log_meal":2}
            $table->json('intents_distribution')->nullable();

            // Cumulative AI latency sum (divide by requests_count for avg)
            $table->unsignedBigInteger('total_ai_latency_ms')->default(0);

            // Number of requests that resulted in an error
            $table->unsignedInteger('errors_count')->default(0);

            $table->timestamps();

            // Unique constraint: one row per user+channel+day
            $table->unique(['user_id', 'channel', 'date']);
            $table->index(['date', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_analytics');
    }
};
