<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts collectibility_status values to the five confirmed labels per DEC-007:
     * - 'current' -> 'lancar'
     * - 'substandard' -> 'kurang_lancar'
     * - 'loss' -> 'bermasalah'
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('agreements')
                ->where('collectibility_status', 'current')
                ->update(['collectibility_status' => 'lancar']);

            DB::table('agreements')
                ->where('collectibility_status', 'substandard')
                ->update(['collectibility_status' => 'kurang_lancar']);

            DB::table('agreements')
                ->where('collectibility_status', 'loss')
                ->update(['collectibility_status' => 'bermasalah']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('agreements')
                ->where('collectibility_status', 'lancar')
                ->update(['collectibility_status' => 'current']);

            DB::table('agreements')
                ->where('collectibility_status', 'kurang_lancar')
                ->update(['collectibility_status' => 'substandard']);

            DB::table('agreements')
                ->where('collectibility_status', 'bermasalah')
                ->update(['collectibility_status' => 'loss']);

            DB::table('agreements')
                ->whereIn('collectibility_status', ['diragukan', 'lunas'])
                ->update(['collectibility_status' => 'unknown']);
        });
    }
};
