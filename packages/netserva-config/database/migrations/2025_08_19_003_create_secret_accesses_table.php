<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secret_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secret_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_type', 50); // 'view', 'copy', 'download', 'api'
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('source')->nullable(); // 'web', 'api', 'cli', 'migration'
            $table->json('additional_context')->nullable(); // Additional context data
            $table->timestamp('accessed_at');

            // Indexes
            $table->index(['secret_id', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
            $table->index('access_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_accesses');
    }
};
