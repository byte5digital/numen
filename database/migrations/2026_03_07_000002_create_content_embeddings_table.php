<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('
                CREATE TABLE IF NOT EXISTS content_embeddings (
                    id              CHAR(26) PRIMARY KEY,
                    content_id      CHAR(26) NOT NULL,
                    content_version_id CHAR(26) NOT NULL,
                    chunk_index     INTEGER NOT NULL DEFAULT 0,
                    chunk_type      VARCHAR(32) NOT NULL DEFAULT \'body\',
                    chunk_text      TEXT NOT NULL,
                    embedding       vector(1536) NOT NULL,
                    embedding_model VARCHAR(128) NOT NULL,
                    token_count     INTEGER NOT NULL DEFAULT 0,
                    metadata        JSONB DEFAULT \'{}\'::jsonb,
                    space_id        CHAR(26) NOT NULL,
                    locale          VARCHAR(10) NOT NULL DEFAULT \'en\',
                    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
                    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
                    CONSTRAINT fk_embeddings_content FOREIGN KEY (content_id)
                        REFERENCES contents(id) ON DELETE CASCADE,
                    CONSTRAINT fk_embeddings_version FOREIGN KEY (content_version_id)
                        REFERENCES content_versions(id) ON DELETE CASCADE,
                    UNIQUE (content_version_id, chunk_index)
                )
            ');

            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_embeddings_vector ON content_embeddings
                    USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)
            ');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_embeddings_content ON content_embeddings (content_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_embeddings_space ON content_embeddings (space_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_embeddings_locale ON content_embeddings (locale)');
        } else {
            // Fallback for SQLite/MySQL: store embedding as JSON text (no vector search, graceful degradation)
            Schema::create('content_embeddings', function ($table) {
                $table->ulid('id')->primary();
                $table->ulid('content_id');
                $table->ulid('content_version_id');
                $table->integer('chunk_index')->default(0);
                $table->string('chunk_type', 32)->default('body');
                $table->text('chunk_text');
                $table->text('embedding');  // JSON-encoded float array (fallback)
                $table->string('embedding_model', 128);
                $table->integer('token_count')->default(0);
                $table->json('metadata')->nullable();
                $table->ulid('space_id');
                $table->string('locale', 10)->default('en');
                $table->timestamps();

                $table->unique(['content_version_id', 'chunk_index']);
                $table->index('content_id');
                $table->index('space_id');
                $table->index('locale');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_embeddings');
    }
};
