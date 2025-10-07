<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_visualizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['line', 'bar', 'pie', 'table', 'metric']);
            $table->json('metric_ids'); // Array of metric IDs to display
            $table->json('config')->nullable(); // Minimal chart configuration
            $table->integer('refresh_interval')->default(300); // seconds
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_visualizations');
    }
};
