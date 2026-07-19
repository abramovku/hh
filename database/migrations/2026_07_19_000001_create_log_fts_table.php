<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection(config('logtodb.connection'));
        $table = config('logtodb.collection', 'log');

        $connection->statement("CREATE VIRTUAL TABLE log_fts USING fts5(message, context, content='{$table}', content_rowid='id')");

        $connection->statement("INSERT INTO log_fts(rowid, message, context) SELECT id, message, context FROM {$table}");

        $connection->statement("CREATE TRIGGER log_fts_after_insert AFTER INSERT ON {$table} BEGIN
            INSERT INTO log_fts(rowid, message, context) VALUES (new.id, new.message, new.context);
        END");

        $connection->statement("CREATE TRIGGER log_fts_after_delete AFTER DELETE ON {$table} BEGIN
            INSERT INTO log_fts(log_fts, rowid, message, context) VALUES ('delete', old.id, old.message, old.context);
        END");

        $connection->statement("CREATE TRIGGER log_fts_after_update AFTER UPDATE ON {$table} BEGIN
            INSERT INTO log_fts(log_fts, rowid, message, context) VALUES ('delete', old.id, old.message, old.context);
            INSERT INTO log_fts(rowid, message, context) VALUES (new.id, new.message, new.context);
        END");
    }

    public function down(): void
    {
        $connection = DB::connection(config('logtodb.connection'));

        $connection->statement('DROP TRIGGER IF EXISTS log_fts_after_insert');
        $connection->statement('DROP TRIGGER IF EXISTS log_fts_after_delete');
        $connection->statement('DROP TRIGGER IF EXISTS log_fts_after_update');
        $connection->statement('DROP TABLE IF EXISTS log_fts');
    }
};
