<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id');
            $table->date('summary_date');
            $table->bigInteger('total_spending')->default(0);
            $table->bigInteger('total_income')->default(0);
            $table->integer('total_calories')->default(0);
            $table->float('total_protein')->default(0);
            $table->float('total_carbs')->default(0);
            $table->float('total_fat')->default(0);
            $table->json('spending_by_category')->nullable();
            $table->json('meals')->nullable();
            $table->timestamps();

            $table->unique(['telegram_chat_id', 'summary_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
