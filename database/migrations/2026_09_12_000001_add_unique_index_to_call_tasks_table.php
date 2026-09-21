<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * One Twin autoCall per (date, type). Existing duplicates (legacy race) are collapsed
     * to the lowest id before the index is added.
     */
    public function up(): void
    {
        $duplicates = DB::table('call_tasks')
            ->select('date', 'type', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('date', 'type')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $row) {
            DB::table('call_tasks')
                ->where('date', $row->date)
                ->where('type', $row->type)
                ->where('id', '<>', $row->keep_id)
                ->delete();
        }

        Schema::table('call_tasks', function (Blueprint $table) {
            $table->unique(['date', 'type'], 'call_tasks_date_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('call_tasks', function (Blueprint $table) {
            $table->dropUnique('call_tasks_date_type_unique');
        });
    }
};
