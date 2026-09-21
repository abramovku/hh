<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            return; // FULLTEXT is MySQL-only; SQLite is used by the test suite.
        }

        Schema::connection(config('logtodb.connection') ?: null)
            ->table(config('logtodb.collection', 'log'), function (Blueprint $table) {
                $table->fullText('message');
                $table->fullText('context');
            });
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        Schema::connection(config('logtodb.connection') ?: null)
            ->table(config('logtodb.collection', 'log'), function (Blueprint $table) {
                $table->dropFullText(['message']);
                $table->dropFullText(['context']);
            });
    }

    private function isSqlite(): bool
    {
        return Schema::connection(config('logtodb.connection') ?: null)->getConnection()->getDriverName() === 'sqlite';
    }
};
