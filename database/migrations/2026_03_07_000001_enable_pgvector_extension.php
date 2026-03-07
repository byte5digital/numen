<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Enable pgvector extension on PostgreSQL.
     * Silently skips on SQLite, MySQL, and any other driver.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            // Not PostgreSQL — pgvector is not supported; skip
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            Log::info('pgvector extension enabled');
        } catch (\Throwable $e) {
            // Extension may already exist or pg_vector is not installed — non-fatal
            Log::debug('pgvector extension could not be enabled: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        try {
            DB::statement('DROP EXTENSION IF EXISTS vector');
        } catch (\Throwable $e) {
            Log::debug('pgvector extension could not be dropped: '.$e->getMessage());
        }
    }
};
