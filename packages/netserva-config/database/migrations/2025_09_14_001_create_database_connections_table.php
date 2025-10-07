<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();

            // Basic connection information
            $table->string('name');
            $table->string('host')->default('localhost');
            $table->integer('port');
            $table->enum('engine', ['mysql', 'postgresql', 'sqlite'])->default('mysql');

            // Authentication
            $table->string('username');
            $table->string('password'); // Encrypted

            // SSL configuration
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_cert_path')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['host', 'port']);
            $table->index(['engine', 'is_active']);
            $table->index(['is_active']);

            // Unique constraint
            $table->unique(['host', 'port', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
