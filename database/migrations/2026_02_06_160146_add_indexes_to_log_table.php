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
        Schema::table('log', function (Blueprint $table) {
            // Индекс для быстрого поиска и удаления старых логов
            $table->index('created_at', 'idx_log_created_at');

            // Индекс для фильтрации по каналу логирования
            $table->index('channel', 'idx_log_channel');

            // Индекс для фильтрации по уровню логирования
            $table->index('level_name', 'idx_log_level_name');

            // Составной индекс для комплексных запросов (канал + дата)
            $table->index(['channel', 'created_at'], 'idx_log_channel_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log', function (Blueprint $table) {
            $table->dropIndex('idx_log_created_at');
            $table->dropIndex('idx_log_channel');
            $table->dropIndex('idx_log_level_name');
            $table->dropIndex('idx_log_channel_created_at');
        });
    }
};
