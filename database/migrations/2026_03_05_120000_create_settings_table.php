<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->boolean('encrypted')->default(false); // API keys are stored encrypted
            $table->string('group')->default('general'); // 'ai_providers' | 'ai_models' | 'cost_limits' | 'general'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
