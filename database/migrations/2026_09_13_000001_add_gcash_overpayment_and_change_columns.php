<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('gcash_tendered', 12, 2)->nullable()->after('gcash_amount');
            $table->decimal('cash_change', 12, 2)->default(0)->after('gcash_tendered');
            $table->decimal('change_fee', 12, 2)->default(0)->after('cash_change');
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->decimal('gcash_tendered', 12, 2)->nullable()->after('gcash_amount');
            $table->decimal('cash_change', 12, 2)->default(0)->after('gcash_tendered');
            $table->decimal('change_fee', 12, 2)->default(0)->after('cash_change');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gcash_tendered', 'cash_change', 'change_fee']);
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropColumn(['gcash_tendered', 'cash_change', 'change_fee']);
        });
    }
};
