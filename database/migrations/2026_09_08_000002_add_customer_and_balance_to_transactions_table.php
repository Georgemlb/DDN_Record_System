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
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->nullOnDelete();
            $table->decimal('amount_paid', 12, 2)->default(0)->after('amount');
            $table->decimal('balance', 12, 2)->default(0)->after('amount_paid');
            $table->string('payment_status', 20)->default('paid')->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['amount_paid', 'balance', 'payment_status']);
        });
    }
};
