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
        Schema::table('airline_markups', function (Blueprint $table) {
            $table->string('service_type', 20)->default('flight')->after('id');
            $table->string('flight_type', 20)->nullable()->change();
            $table->string('markup_type', 20)->default('fixed')->after('flight_type');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('trip_type', 20)->nullable()->default('one_way')->after('pax');
            $table->string('carrier_category', 20)->nullable()->after('flight_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['trip_type', 'carrier_category']);
        });

        Schema::table('airline_markups', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'markup_type']);
        });
    }
};
