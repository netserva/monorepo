<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_profiles', function (Blueprint $table) {
            $table->id();

            // Profile identification
            $table->string('profile_type')->index(); // provider, server, host, vhost
            $table->string('profile_name')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // File information
            $table->string('filepath')->nullable();
            $table->longText('content');

            // Extracted information
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('status')->default('active')->index();

            // Migration tracking
            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('file_modified_at')->nullable();
            $table->string('checksum', 32)->nullable();

            $table->timestamps();

            // Ensure unique profile type/name combinations
            $table->unique(['profile_type', 'profile_name']);

            // Indexes for common queries
            $table->index(['profile_type', 'status']);
            $table->index(['category', 'status']);
            $table->index('migrated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_profiles');
    }
};
