<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vhosts', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();

            // Host identification
            $table->string('host')->nullable();

            // Domain configuration
            $table->json('domain_aliases')->nullable();
            $table->string('document_root')->nullable();

            // Web server configuration
            $table->string('web_server')->default('nginx');

            // SSL configuration
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_cert_path')->nullable();
            $table->string('ssl_key_path')->nullable();

            // PHP configuration
            $table->boolean('php_enabled')->default(true);
            $table->string('php_version')->default('8.3');

            // Database configuration
            $table->boolean('database_enabled')->default(false);
            $table->string('database_name')->nullable();

            // Email configuration
            $table->boolean('email_enabled')->default(false);

            // Status
            $table->string('status')->default('inactive');
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('host');
            $table->index('status');
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vhosts');
    }
};
