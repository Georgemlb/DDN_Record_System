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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('name');
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        $firstUser = DB::table('users')->first();
        if ($firstUser) {
            DB::table('users')
                ->where('id', $firstUser->id)
                ->update([
                    'role' => 'tech_admin',
                    'username' => $firstUser->username ?? 'admin',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'username']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
