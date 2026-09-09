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
        Schema::create('gcash_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index(); // customer_payment, remittance_cash_out, remittance_cash_in, government_disbursement, expense_payment, portal_reload, adjustment
            $table->decimal('amount', 12, 2);
            $table->decimal('running_balance', 12, 2)->default(0);
            $table->boolean('is_balance_anchor')->default(false)->index();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->string('reference_number', 100)->nullable()->index();
            $table->date('entry_date')->index();
            $table->integer('sort_order')->default(1)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gcash_ledger_entries');
    }
};
