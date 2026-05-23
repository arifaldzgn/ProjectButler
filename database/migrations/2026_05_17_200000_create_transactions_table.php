<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['expense', 'income', 'savings']);
            $table->bigInteger('amount'); // stored in IDR
            $table->string('currency', 5)->default('IDR');
            $table->string('category')->nullable(); // food, transport, entertainment, etc.
            $table->string('description');
            $table->string('source')->nullable(); // cash, bank, paypal, etc.
            $table->string('telegram_chat_id');
            $table->text('raw_message');
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['telegram_chat_id', 'transaction_date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
