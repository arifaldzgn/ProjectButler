<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');      // 0-100
            $table->json('components');                // breakdown per factor
            $table->timestamp('calculated_at');

            $table->index(['user_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_health_scores');
    }
};
