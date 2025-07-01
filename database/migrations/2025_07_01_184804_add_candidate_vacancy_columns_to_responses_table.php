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
        Schema::table('responses', function (Blueprint $table) {
            $table->bigInteger('vacancy_estaff')->nullable()->after('manager_id');
            $table->bigInteger('candidate_estaff')->nullable()->after('vacancy_estaff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn('vacancy_estaff');
            $table->dropColumn('candidate_estaff');
        });
    }
};
