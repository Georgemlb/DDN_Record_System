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
        Schema::table('flight_portal_deposits', function (Blueprint $table) {
            $table->boolean('is_balance_anchor')->default(false)->after('running_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_portal_deposits', function (Blueprint $table) {
            $table->dropColumn('is_balance_anchor');
        });
    }
};
