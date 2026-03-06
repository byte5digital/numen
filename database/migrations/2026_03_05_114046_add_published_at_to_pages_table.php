<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('meta');
        });

        // Backfill: set published_at for existing published pages
        DB::table('pages')->where('status', 'published')->whereNull('published_at')
            ->update(['published_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
