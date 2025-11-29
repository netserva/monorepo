<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vhost_configurations', function (Blueprint $table) {
            $table->id();

            // Core identification
            $table->string('vnode')->index();
            $table->string('vhost')->index();
            $table->string('filepath')->nullable();

            // Configuration data
            $table->json('variables');

            // Migration tracking
            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('file_modified_at')->nullable();
            $table->string('checksum', 32)->nullable();

            $table->timestamps();

            // Ensure unique vnode/vhost combinations
            $table->unique(['vnode', 'vhost']);

            // Indexes for common queries
            $table->index(['vnode', 'vhost']);
            $table->index('migrated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vhost_configurations');
    }
};
