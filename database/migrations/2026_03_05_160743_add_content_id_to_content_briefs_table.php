<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_briefs', function (Blueprint $table) {
            $table->string('content_id')->nullable()->after('space_id');
            $table->foreign('content_id')->references('id')->on('contents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_briefs', function (Blueprint $table) {
            $table->dropForeign(['content_id']);
            $table->dropColumn('content_id');
        });
    }
};
