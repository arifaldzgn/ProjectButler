<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->decimal('protein_g', 6, 1)->nullable()->after('calories');
            $table->decimal('carbs_g', 6, 1)->nullable()->after('protein_g');
            $table->decimal('fat_g', 6, 1)->nullable()->after('carbs_g');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['protein_g', 'carbs_g', 'fat_g']);
        });
    }
};
