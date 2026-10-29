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
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->index(['payment_date', 'transaction_id'], 'idx_payments_date_tx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['transaction_date', 'id'], 'idx_tx_date_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['expense_date', 'id'], 'idx_expenses_date_id');
        });

        Schema::table('gcash_ledger_entries', function (Blueprint $table) {
            $table->index(['entry_date', 'sort_order', 'id'], 'idx_gcash_ledger_date_order_id');
        });

        Schema::table('flight_portal_deposits', function (Blueprint $table) {
            $table->index(['entry_date', 'sort_order', 'id'], 'idx_portal_deposits_date_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_date_tx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_tx_date_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_date_id');
        });

        Schema::table('gcash_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('idx_gcash_ledger_date_order_id');
        });

        Schema::table('flight_portal_deposits', function (Blueprint $table) {
            $table->dropIndex('idx_portal_deposits_date_order_id');
        });
    }
};
