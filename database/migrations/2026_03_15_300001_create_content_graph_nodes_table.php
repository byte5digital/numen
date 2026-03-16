<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_graph_nodes')) {
            Schema::create('content_graph_nodes', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('content_id', 26)->unique();
                $table->string('space_id', 26)->index();
                $table->string('locale', 10)->default('en');
                $table->json('entity_labels')->nullable();
                $table->string('cluster_id', 26)->nullable()->index();
                $table->json('node_metadata')->nullable();
                $table->timestamp('indexed_at')->nullable();
                $table->timestamps();

                $table->index(['space_id', 'locale']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_graph_nodes');
    }
};
