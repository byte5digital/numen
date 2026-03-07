<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill status='published' for content versions that are the current
     * version of their content but were created before the versioning system
     * added the status column (which defaults to 'draft').
     */
    public function up(): void
    {
        DB::table('content_versions')
            ->whereIn('id', function ($query) {
                $query->select('current_version_id')
                    ->from('contents')
                    ->whereNotNull('current_version_id');
            })
            ->where('status', 'draft')
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        // Not reversible — we can't know which were originally draft
    }
};
