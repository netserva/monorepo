<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('databases', function (Blueprint $table) {
            $table->id();

            // Connection association
            $table->foreignId('connection_id')
                ->constrained('database_connections')
                ->cascadeOnDelete();

            // Database information
            $table->string('name');
            $table->string('charset')->default('utf8mb4');
            $table->string('collation')->default('utf8mb4_unicode_ci');

            // Storage info
            $table->bigInteger('size_bytes')->default(0);

            // Backup configuration
            $table->boolean('backup_enabled')->default(true);
            $table->string('backup_schedule')->default('0 2 * * *'); // Daily at 2 AM

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['connection_id', 'is_active']);
            $table->index(['backup_enabled']);
            $table->index(['is_active']);

            // Unique constraint
            $table->unique(['connection_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
