<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plugins')) {
            Schema::create('plugins', function (Blueprint $table) {
                $table->string('id', 26)->primary();
                $table->string('name', 255)->unique();
                $table->string('display_name', 255);
                $table->string('version', 50);
                $table->text('description')->nullable();
                $table->json('manifest');
                $table->string('status', 20)->default('discovered');
                $table->timestamp('installed_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
