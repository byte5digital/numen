<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->string('hero_image_id')->nullable()->after('metadata');
            $table->foreign('hero_image_id')->references('id')->on('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropForeign(['hero_image_id']);
            $table->dropColumn('hero_image_id');
        });
    }
};
