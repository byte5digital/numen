<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurposed_contents', function (Blueprint $table) {
            $table->foreignId('batch_id')
                ->nullable()
                ->after('format_template_id')
                ->constrained('repurposing_batches')
                ->nullOnDelete();

            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('repurposed_contents', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
