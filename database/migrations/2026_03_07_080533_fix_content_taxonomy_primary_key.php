<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreate content_taxonomy without a separate id column.
 * Standard pivot tables use a composite primary key (content_id + term_id).
 * The original migration added an id column which breaks Laravel's BelongsToMany attach/sync/detach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('content_taxonomy');

        Schema::create('content_taxonomy', function (Blueprint $table): void {
            $table->ulid('content_id');
            $table->ulid('term_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('auto_assigned')->default(false);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamps();

            $table->primary(['content_id', 'term_id']);
            $table->index('term_id');
            $table->index('content_id');
            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('taxonomy_terms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::drop('content_taxonomy');

        Schema::create('content_taxonomy', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('content_id');
            $table->ulid('term_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('auto_assigned')->default(false);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['content_id', 'term_id']);
            $table->index('term_id');
            $table->index('content_id');
            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('taxonomy_terms')->cascadeOnDelete();
        });
    }
};
