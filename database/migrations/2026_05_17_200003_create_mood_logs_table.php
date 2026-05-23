<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('mood', ['great', 'good', 'okay', 'bad', 'terrible']);
            $table->integer('energy_level')->nullable(); // 1-5
            $table->text('note')->nullable();
            $table->string('telegram_chat_id');
            $table->date('log_date');
            $table->timestamps();

            $table->index(['telegram_chat_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};
