<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_graph_edges')) {
            Schema::create('content_graph_edges', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('source_id', 26)->index();
                $table->string('target_id', 26)->index();
                $table->string('edge_type', 32)->index();
                $table->float('weight')->default(0.0);
                $table->json('edge_metadata')->nullable();
                $table->timestamps();

                $table->unique(['source_id', 'target_id', 'edge_type']);
                $table->index(['source_id', 'weight']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_graph_edges');
    }
};
