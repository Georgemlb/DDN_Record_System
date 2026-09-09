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
        Schema::table('daily_settlements', function (Blueprint $table) {
            $table->decimal('opening_gcash', 12, 2)->default(0)->after('discrepancy');
            $table->decimal('gcash_in', 12, 2)->default(0)->after('opening_gcash');
            $table->decimal('gcash_out', 12, 2)->default(0)->after('gcash_in');
            $table->decimal('expected_gcash', 12, 2)->default(0)->after('gcash_out');
            $table->decimal('actual_gcash', 12, 2)->nullable()->after('expected_gcash');
            $table->decimal('gcash_discrepancy', 12, 2)->nullable()->after('actual_gcash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_settlements', function (Blueprint $table) {
            $table->dropColumn([
                'opening_gcash',
                'gcash_in',
                'gcash_out',
                'expected_gcash',
                'actual_gcash',
                'gcash_discrepancy',
            ]);
        });
    }
};
