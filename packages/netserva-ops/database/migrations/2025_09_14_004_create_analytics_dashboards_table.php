<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('widgets'); // Array of {viz_id, x, y, width, height}
            $table->integer('layout_columns')->default(12); // 12-column grid
            $table->integer('refresh_interval')->default(300); // seconds
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_public', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_dashboards');
    }
};
