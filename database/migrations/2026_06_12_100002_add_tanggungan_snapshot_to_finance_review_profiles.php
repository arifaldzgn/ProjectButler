<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_review_profiles', function (Blueprint $table) {
            $table->json('tanggungan_snapshot')->nullable()->after('tanggungan_count');
        });
    }

    public function down(): void
    {
        Schema::table('finance_review_profiles', function (Blueprint $table) {
            $table->dropColumn('tanggungan_snapshot');
        });
    }
};
