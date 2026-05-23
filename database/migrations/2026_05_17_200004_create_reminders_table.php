<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id');
            $table->text('message');
            $table->dateTime('remind_at');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // daily, weekly, monthly
            $table->boolean('is_sent')->default(false);
            $table->timestamps();

            $table->index(['is_sent', 'remind_at']);
            $table->index('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
