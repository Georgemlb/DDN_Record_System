<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20);
            $table->decimal('cash_amount', 12, 2)->nullable();
            $table->decimal('gcash_amount', 12, 2)->nullable();
            $table->date('payment_date');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // Backfill existing transactions so that amount_paid = amount, balance = 0, and a payment record exists
        $transactions = DB::table('transactions')->get();
        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            DB::table('transactions')->where('id', $tx->id)->update([
                'amount_paid' => $amount,
                'balance' => 0.00,
                'payment_status' => 'paid',
            ]);

            if ($amount > 0) {
                DB::table('transaction_payments')->insert([
                    'transaction_id' => $tx->id,
                    'user_id' => $tx->user_id,
                    'amount' => $amount,
                    'payment_method' => $tx->payment_method ?? 'cash',
                    'cash_amount' => $tx->cash_amount,
                    'gcash_amount' => $tx->gcash_amount,
                    'payment_date' => $tx->transaction_date,
                    'notes' => 'Initial payment (system backfill)',
                    'created_at' => $tx->created_at ?? now(),
                    'updated_at' => $tx->updated_at ?? now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
    }
};
