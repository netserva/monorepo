<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('analytics_data_source_id')
                ->constrained('analytics_data_sources')
                ->cascadeOnDelete();
            $table->text('query'); // SQL query or API endpoint
            $table->decimal('value', 15, 4)->nullable(); // Current value
            $table->timestamp('collected_at')->nullable(); // Last collection time
            $table->enum('frequency', ['hourly', 'daily', 'weekly'])->default('hourly');
            $table->string('unit')->nullable(); // USD, %, count, etc
            $table->enum('type', ['number', 'percentage', 'currency'])->default('number');
            $table->decimal('threshold_warning', 15, 4)->nullable();
            $table->decimal('threshold_critical', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['analytics_data_source_id', 'is_active']);
            $table->index(['frequency', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_metrics');
    }
};
