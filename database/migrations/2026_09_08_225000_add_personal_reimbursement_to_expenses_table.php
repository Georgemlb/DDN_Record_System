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
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('notes');
            $table->string('reimbursement_status', 20)->default('none')->after('is_personal');
            $table->date('reimbursed_at')->nullable()->after('reimbursement_status');
            $table->string('reimbursement_method', 20)->nullable()->after('reimbursed_at');
            $table->text('reimbursement_notes')->nullable()->after('reimbursement_method');

            $table->index('is_personal');
            $table->index('reimbursement_status');
            $table->index('reimbursed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['is_personal']);
            $table->dropIndex(['reimbursement_status']);
            $table->dropIndex(['reimbursed_at']);
            $table->dropColumn([
                'is_personal',
                'reimbursement_status',
                'reimbursed_at',
                'reimbursement_method',
                'reimbursement_notes',
            ]);
        });
    }
};
