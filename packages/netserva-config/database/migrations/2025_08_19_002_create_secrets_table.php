<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', [
                'password',
                'api_key',
                'ssh_private_key',
                'certificate',
                'token',
                'connection_string',
                'environment_variable',
                'other',
            ]);
            $table->text('description')->nullable();
            $table->longText('encrypted_value'); // Encrypted secret data
            $table->string('encryption_method')->default('aes-256-gcm'); // Encryption algorithm used
            $table->text('metadata')->nullable(); // JSON field for additional metadata
            $table->string('environment')->default('production'); // production, staging, development
            $table->string('category')->nullable(); // Group secrets by category/project
            $table->json('tags')->nullable(); // Array of tags for filtering

            // Access control
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable(); // Secret expiration
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0);

            // Infrastructure relationships
            $table->foreignId('infrastructure_node_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ssh_host_reference')->nullable(); // Reference to SSH host slug

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['type', 'environment']);
            $table->index(['category', 'environment']);
            $table->index(['is_active', 'expires_at']);
            $table->index('ssh_host_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
