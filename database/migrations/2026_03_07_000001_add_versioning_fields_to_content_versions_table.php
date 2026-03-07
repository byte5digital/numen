<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            // Named versions: "v1.0 Launch Copy", "v2.0 SEO Update"
            $table->string('label')->nullable()->after('version_number');

            // Version lifecycle status: draft | published | archived | scheduled
            $table->string('status')->default('draft')->after('label');

            // Branch support: which version is this branched from?
            $table->ulid('parent_version_id')->nullable()->after('status');

            // Scheduled publishing
            $table->timestamp('scheduled_at')->nullable()->after('seo_score');

            // Snapshot hash for fast equality checks
            $table->string('content_hash', 64)->nullable()->after('scheduled_at');

            // Soft-lock: who is currently editing this draft?
            $table->ulid('locked_by')->nullable()->after('content_hash');
            $table->timestamp('locked_at')->nullable()->after('locked_by');

            // Foreign key for parent version (branching)
            $table->foreign('parent_version_id')
                ->references('id')->on('content_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            $table->dropForeign(['parent_version_id']);
            $table->dropColumn([
                'label',
                'status',
                'parent_version_id',
                'scheduled_at',
                'content_hash',
                'locked_by',
                'locked_at',
            ]);
        });
    }
};
