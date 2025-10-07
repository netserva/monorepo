<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificate_authorities', function (Blueprint $table) {
            $table->id();

            // Basic information
            $table->string('name', 100); // Let's Encrypt, Custom CA, etc.
            $table->string('slug', 50)->unique(); // letsencrypt, custom-ca, etc.
            $table->text('description')->nullable();

            // CA type and configuration
            $table->enum('ca_type', ['letsencrypt', 'buypass', 'zerossl', 'custom', 'self_signed'])->default('letsencrypt');
            $table->string('acme_directory_url')->nullable(); // ACME directory URL for ACME CAs
            $table->string('acme_tos_url')->nullable(); // Terms of Service URL
            $table->boolean('supports_wildcard')->default(true);
            $table->boolean('supports_ecc')->default(true); // Elliptic Curve Cryptography

            // Rate limiting information
            $table->integer('rate_limit_per_week')->nullable(); // Certificates per week
            $table->integer('rate_limit_per_domain')->nullable(); // Per domain limit
            $table->text('rate_limit_notes')->nullable();

            // Authentication and credentials
            $table->json('auth_config')->nullable(); // API keys, credentials, etc.
            $table->text('account_key')->nullable(); // ACME account private key (encrypted)
            $table->string('account_url')->nullable(); // ACME account URL
            $table->string('account_email')->nullable();

            // CA certificate chain
            $table->text('ca_certificate')->nullable(); // Root CA certificate
            $table->text('intermediate_certificates')->nullable(); // JSON array of intermediate certs

            // Status and preferences
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(100); // Lower number = higher priority

            // Monitoring and validation
            $table->timestamp('last_validated_at')->nullable();
            $table->json('validation_results')->nullable(); // Last validation results
            $table->text('validation_errors')->nullable();

            // Statistics
            $table->integer('certificates_issued')->default(0);
            $table->integer('certificates_renewed')->default(0);
            $table->integer('certificates_failed')->default(0);
            $table->timestamp('last_certificate_issued_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['ca_type', 'is_active']);
            $table->index(['is_default', 'priority']);
            $table->index('last_certificate_issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificate_authorities');
    }
};
