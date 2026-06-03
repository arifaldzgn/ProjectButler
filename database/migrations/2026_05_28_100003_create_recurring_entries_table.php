<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description', 200);
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('type', 30)->default('expense');     // expense, saving, income, meal
            $table->foreignId('account_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_name', 80)->nullable();    // text fallback
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('monthly');
            $table->unsignedTinyInteger('day_of_week')->nullable();   // 0=Sun … 6=Sat (weekly)
            $table->unsignedTinyInteger('day_of_month')->nullable();  // 1-28 (monthly)
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_entries');
    }
};
