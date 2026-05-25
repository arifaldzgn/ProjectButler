<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify ENUM column in entries table to include 'transfer'
        DB::statement("ALTER TABLE entries MODIFY type ENUM('expense','meal','saving','income','bill_payment','sinking_fund_deposit','goal_deposit','debt_payment','transfer') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ENUM column
        DB::statement("ALTER TABLE entries MODIFY type ENUM('expense','meal','saving','income','bill_payment','sinking_fund_deposit','goal_deposit','debt_payment') NOT NULL");
    }
};
