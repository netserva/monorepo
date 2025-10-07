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
        Schema::create('fleet_vhost_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vhost_id')->constrained('fleet_vhosts')->onDelete('cascade');
            $table->string('service_type', 50); // mail, ssh, wordpress, private, ftp, phpmyadmin, hcp
            $table->string('account_name', 200); // email@domain.com, admin, guest, etc.
            $table->string('username', 200)->nullable(); // Login username (often same as account_name)
            $table->text('password'); // PLAIN TEXT for customer support
            $table->string('url', 500)->nullable(); // Admin URLs, SFTP URLs, etc.
            $table->integer('port')->nullable(); // SSH port, FTP port, etc.
            $table->string('path', 500)->nullable(); // SSH path, web path, etc.
            $table->text('notes')->nullable(); // Additional info, alternative usernames
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->unique(['vhost_id', 'service_type', 'account_name'], 'unique_vhost_service_account');
            $table->index(['vhost_id', 'service_type'], 'idx_vhost_service');
            $table->index(['service_type', 'account_name'], 'idx_service_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_vhost_credentials');
    }
};
