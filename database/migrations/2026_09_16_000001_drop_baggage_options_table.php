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
            if (Schema::hasColumn('transactions', 'baggage_option_id')) {
                $table->dropForeign(['baggage_option_id']);
                $table->dropColumn('baggage_option_id');
            }
        });

        Schema::dropIfExists('baggage_options');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('baggage_options')) {
            Schema::create('baggage_options', function (Blueprint $table) {
                $table->id();
                $table->string('airline')->nullable();
                $table->string('label');
                $table->unsignedSmallInteger('weight_kg')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'baggage_option_id')) {
                $table->foreignId('baggage_option_id')->nullable()->after('provider')->constrained('baggage_options')->nullOnDelete();
            }
        });
    }
};
