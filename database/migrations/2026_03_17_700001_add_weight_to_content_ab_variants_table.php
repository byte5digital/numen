<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_ab_variants') && ! Schema::hasColumn('content_ab_variants', 'weight')) {
            Schema::table('content_ab_variants', function (Blueprint $table) {
                $table->decimal('weight', 5, 4)->nullable()->after('conversion_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('content_ab_variants', 'weight')) {
            Schema::table('content_ab_variants', function (Blueprint $table) {
                $table->dropColumn('weight');
            });
        }
    }
};
