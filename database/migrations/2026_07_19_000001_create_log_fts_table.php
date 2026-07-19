<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('logtodb.connection') ?: null)
            ->table(config('logtodb.collection', 'log'), function (Blueprint $table) {
                $table->fullText('message');
                $table->fullText('context');
            });
    }

    public function down(): void
    {
        Schema::connection(config('logtodb.connection') ?: null)
            ->table(config('logtodb.collection', 'log'), function (Blueprint $table) {
                $table->dropFullText(['message']);
                $table->dropFullText(['context']);
            });
    }
};
