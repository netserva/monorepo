<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_credentials', function (Blueprint $table) {
            $table->id();

            // Database association
            $table->foreignId('database_id')
                ->constrained('databases')
                ->cascadeOnDelete();

            // User credentials
            $table->string('username');
            $table->string('password'); // Encrypted
            $table->string('host')->default('localhost');

            // Privileges (simple JSON array)
            $table->json('privileges')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['database_id', 'is_active']);
            $table->index(['username']);
            $table->index(['is_active']);

            // Unique constraint
            $table->unique(['database_id', 'username', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_credentials');
    }
};
