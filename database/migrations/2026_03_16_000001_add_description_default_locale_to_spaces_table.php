<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('spaces', 'description')) {
            Schema::table('spaces', function (Blueprint $table) {
                $table->text('description')->nullable()->after('slug');
                $table->string('default_locale', 10)->default('en')->after('description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['description', 'default_locale']);
        });
    }
};
