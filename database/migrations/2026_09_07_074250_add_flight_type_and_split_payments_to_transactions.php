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
            $table->enum('flight_type', ['local', 'international'])->nullable()->after('provider');
            $table->decimal('cash_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('gcash_amount', 12, 2)->nullable()->after('cash_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['flight_type', 'cash_amount', 'gcash_amount']);
        });
    }
};
