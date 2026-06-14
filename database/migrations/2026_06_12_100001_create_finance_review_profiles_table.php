<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_review_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Step 1 — Profil & Pendapatan
            $table->string('domisili')->nullable();
            $table->string('domisili_key')->nullable();
            $table->unsignedBigInteger('gaji_bersih')->nullable();

            // Step 2 — Tempat Tinggal
            $table->enum('housing_status', ['kpr', 'sewa', 'kontrak', 'ortu', 'lainnya'])->nullable();
            $table->unsignedBigInteger('housing_cost')->nullable();

            // Step 3 — Kebutuhan Makan
            $table->tinyInteger('cook_percentage')->nullable();
            $table->unsignedBigInteger('food_base_monthly')->nullable();
            $table->enum('hangout_frequency', ['jarang', '1-2x', '3-4x', 'setiap-hari'])->nullable();

            // Step 4 — Transportasi
            $table->string('transport_type')->nullable();
            $table->tinyInteger('commute_km_daily')->nullable();
            $table->unsignedBigInteger('transport_monthly')->nullable();

            // Step 5 — Tagihan & Langganan (JSON snapshots)
            $table->json('bills_snapshot')->nullable();
            $table->json('recurring_snapshot')->nullable();

            // Step 6 — Tanggungan & Gaya Hidup
            $table->tinyInteger('tanggungan_count')->default(0);
            $table->json('tanggungan_snapshot')->nullable();        // [{name,amount}]
            $table->unsignedBigInteger('family_remittance')->nullable();
            $table->unsignedBigInteger('rokok_monthly')->nullable();
            $table->unsignedBigInteger('gym_monthly')->nullable();
            $table->unsignedBigInteger('asuransi_monthly')->nullable();
            $table->unsignedBigInteger('mudik_annual')->nullable();

            // Step 7 — Cicilan & Hutang
            $table->json('debts_snapshot')->nullable();

            // Progress & cached AI output
            $table->tinyInteger('last_completed_step')->default(0);
            $table->timestamp('review_completed_at')->nullable();
            $table->text('ai_insights')->nullable();
            $table->timestamp('insights_generated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_review_profiles');
    }
};
