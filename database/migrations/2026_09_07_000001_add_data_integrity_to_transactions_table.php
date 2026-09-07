<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('reference_number', 30)->nullable()->after('user_id')->unique();
            $table->softDeletes();
        });

        // Backfill user_id for existing records
        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId) {
            DB::table('transactions')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // Backfill sequential reference numbers for existing records
        $transactions = DB::table('transactions')
            ->whereNull('reference_number')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'transaction_date']);

        $counters = [];
        foreach ($transactions as $tx) {
            $datePart = Carbon::parse($tx->transaction_date)->format('Ymd');
            $counters[$datePart] = ($counters[$datePart] ?? 0) + 1;
            $ref = sprintf('TRX-%s-%04d', $datePart, $counters[$datePart]);

            DB::table('transactions')
                ->where('id', $tx->id)
                ->update(['reference_number' => $ref]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'reference_number', 'deleted_at']);
        });
    }
};
