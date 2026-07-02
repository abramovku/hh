<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('log', ['created_at'])) {
            Schema::table('log', function (Blueprint $table) {
                $table->index('created_at', 'idx_log_created_at');
            });
        }

        if (! Schema::hasIndex('log', ['level_name', 'created_at'])) {
            Schema::table('log', function (Blueprint $table) {
                $table->index(['level_name', 'created_at'], 'idx_log_level_name_created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log', function (Blueprint $table) {
            if (Schema::hasIndex('log', ['level_name', 'created_at'])) {
                $table->dropIndex('idx_log_level_name_created_at');
            }
        });
    }
};
