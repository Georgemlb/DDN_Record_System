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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 50)->default('airline')->after('id')->index();
            $table->string('payment_method', 20)->default('cash')->after('type');
            $table->unsignedSmallInteger('pax')->nullable()->change();
            $table->foreignId('baggage_option_id')->nullable()->after('provider')->constrained('baggage_options')->nullOnDelete();
            $table->string('psa_document_type', 20)->nullable()->after('baggage_option_id');
            $table->foreignId('online_assistance_type_id')->nullable()->after('psa_document_type')->constrained('online_assistance_types')->nullOnDelete();
            $table->decimal('third_party_payment', 12, 2)->nullable()->after('amount');
            $table->decimal('ddn_amount', 12, 2)->nullable()->after('third_party_payment');
            $table->decimal('employee_share', 12, 2)->nullable()->after('ddn_amount');
        });

        // Set default values for any existing records
        DB::table('transactions')->whereNull('ddn_amount')->update([
            'type' => 'airline',
            'payment_method' => 'cash',
            'ddn_amount' => DB::raw('amount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['baggage_option_id']);
            $table->dropForeign(['online_assistance_type_id']);
            $table->dropColumn([
                'type',
                'payment_method',
                'baggage_option_id',
                'psa_document_type',
                'online_assistance_type_id',
                'third_party_payment',
                'ddn_amount',
                'employee_share',
            ]);
            $table->unsignedSmallInteger('pax')->nullable(false)->change();
        });
    }
};

