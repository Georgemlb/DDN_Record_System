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
        Schema::table('online_assistance_types', function (Blueprint $table) {
            $table->decimal('disbursement_amount', 12, 2)->nullable()->after('has_disbursement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_assistance_types', function (Blueprint $table) {
            $table->dropColumn('disbursement_amount');
        });
    }
};
