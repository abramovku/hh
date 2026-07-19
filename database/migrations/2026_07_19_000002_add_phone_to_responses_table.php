<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->index()->after('candidate_estaff');
        });

        DB::statement("
            UPDATE responses r
            JOIN hh_meta m ON m.response_id = r.id AND m.`key` = 'cell'
            SET r.phone = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                SUBSTRING_INDEX(m.value, ',', 1), '+', ''), '(', ''), ')', ''), '-', ''), ' ', '')
        ");

        DB::statement("
            INSERT INTO hh_meta (response_id, `key`, value)
            SELECT m.response_id, 'cell_clean', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                SUBSTRING_INDEX(m.value, ',', 1), '+', ''), '(', ''), ')', ''), '-', ''), ' ', '')
            FROM hh_meta m
            WHERE m.`key` = 'cell'
              AND NOT EXISTS (SELECT 1 FROM hh_meta e WHERE e.response_id = m.response_id AND e.`key` = 'cell_clean')
        ");
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        DB::table('hh_meta')->where('key', 'cell_clean')->delete();
    }
};
