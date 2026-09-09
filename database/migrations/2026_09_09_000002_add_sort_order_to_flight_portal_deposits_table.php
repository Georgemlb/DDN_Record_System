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
        Schema::table('flight_portal_deposits', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('entry_date')->index();
        });

        // Initialize sort_order for existing records grouped by date
        $dates = DB::table('flight_portal_deposits')
            ->select('entry_date')
            ->distinct()
            ->pluck('entry_date');

        foreach ($dates as $date) {
            $records = DB::table('flight_portal_deposits')
                ->where('entry_date', $date)
                ->orderByRaw("CASE WHEN type = 'top_up' THEN 1 WHEN type = 'adjustment' THEN 2 ELSE 3 END")
                ->orderBy('id', 'asc')
                ->get();

            $order = 1;
            foreach ($records as $record) {
                DB::table('flight_portal_deposits')
                    ->where('id', $record->id)
                    ->update(['sort_order' => $order++]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_portal_deposits', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
