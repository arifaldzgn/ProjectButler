<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_logs', function (Blueprint $table) {
            $table->id();
            $table->string('food_name');
            $table->integer('calories')->nullable();
            $table->float('protein_g')->nullable();
            $table->float('carbs_g')->nullable();
            $table->float('fat_g')->nullable();
            $table->float('fiber_g')->nullable();
            $table->float('serving_size')->nullable();
            $table->string('serving_unit')->nullable(); // gram, porsi, pcs
            $table->string('meal_type')->nullable(); // breakfast, lunch, dinner, snack
            $table->string('telegram_chat_id');
            $table->text('raw_message');
            $table->date('log_date');
            $table->timestamps();

            $table->index(['telegram_chat_id', 'log_date']);
            $table->index('meal_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_logs');
    }
};
