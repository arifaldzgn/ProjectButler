<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_commands', function (Blueprint $table) {
            $table->id();
            $table->string('alias');          // trigger keyword (e.g. 'saldo', 'cek uang')
            $table->string('handler');        // handler method name in MessageRouter (e.g. 'handleQueryBalance')
            $table->string('description')->nullable(); // human-readable label for admin UI
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('alias');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_commands');
    }
};
