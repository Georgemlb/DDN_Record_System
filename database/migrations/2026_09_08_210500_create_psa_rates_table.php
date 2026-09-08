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
        Schema::create('psa_rates', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->unique(); // BC, MC, DC, Cenomar
            $table->string('name'); // Birth Certificate, Marriage Certificate, etc.
            $table->decimal('price', 10, 2); // Customer price
            $table->decimal('officer_fee', 10, 2); // PSA officer payment
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psa_rates');
    }
};
