<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();

            // Certificate identity
            $table->string('common_name'); // Primary domain (example.com)
            $table->json('subject_alternative_names')->nullable(); // Additional domains [www.example.com, api.example.com]
            $table->string('certificate_type', 20)->default('domain'); // domain, wildcard, multi_domain
            $table->string('fingerprint_sha1', 40)->nullable(); // SHA1 fingerprint for identification
            $table->string('fingerprint_sha256', 64)->nullable(); // SHA256 fingerprint

            // Certificate Authority relationship
            $table->foreignId('ssl_certificate_authority_id')
                ->constrained('ssl_certificate_authorities')
                ->cascadeOnDelete();

            // Certificate content and keys
            $table->text('certificate_pem'); // The actual certificate
            $table->text('certificate_chain_pem')->nullable(); // Full certificate chain
            $table->text('private_key_pem'); // Private key (encrypted)
            $table->text('csr_pem')->nullable(); // Certificate Signing Request

            // Certificate properties
            $table->string('key_type', 10)->default('rsa'); // rsa, ecdsa
            $table->integer('key_size')->default(2048); // 2048, 3072, 4096 for RSA; 256, 384 for ECDSA
            $table->string('signature_algorithm', 50)->nullable(); // SHA256withRSA, etc.

            // Validity period
            $table->timestamp('not_valid_before');
            $table->timestamp('not_valid_after');
            $table->integer('validity_days')->default(90); // Certificate validity period

            // Auto-renewal configuration
            $table->boolean('auto_renew')->default(true);
            $table->integer('renew_days_before_expiry')->default(30); // Renew 30 days before expiry
            $table->timestamp('next_renewal_attempt_at')->nullable();
            $table->integer('renewal_attempts')->default(0);
            $table->timestamp('last_renewal_attempt_at')->nullable();
            $table->text('last_renewal_error')->nullable();

            // ACME specific fields
            $table->string('acme_order_url')->nullable(); // ACME order URL
            $table->json('acme_challenges')->nullable(); // ACME challenges data
            $table->enum('acme_status', ['pending', 'processing', 'valid', 'invalid', 'expired', 'revoked'])->nullable();

            // Deployment tracking
            $table->json('deployed_to_servers')->nullable(); // List of server IDs where this cert is deployed
            $table->timestamp('last_deployed_at')->nullable();
            $table->integer('deployment_count')->default(0);

            // Certificate status and health
            $table->enum('status', ['pending', 'active', 'expired', 'revoked', 'failed', 'renewing'])->default('pending');
            $table->timestamp('status_checked_at')->nullable();
            $table->json('health_check_results')->nullable(); // Last health check results
            $table->text('status_notes')->nullable();

            // Usage tracking
            $table->json('used_by_services')->nullable(); // nginx, apache, haproxy, etc.
            $table->boolean('is_wildcard')->default(false);
            $table->boolean('is_self_signed')->default(false);

            // Monitoring and alerts
            $table->boolean('monitor_expiry')->default(true);
            $table->boolean('alert_on_expiry')->default(true);
            $table->integer('alert_days_before_expiry')->default(7);
            $table->timestamp('last_alert_sent_at')->nullable();

            // Certificate validation
            $table->json('validation_methods')->nullable(); // http-01, dns-01, tls-alpn-01
            $table->timestamp('last_validated_at')->nullable();
            $table->json('validation_results')->nullable();

            // Revocation information
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();

            // Administrative fields
            $table->string('requested_by')->nullable(); // User who requested the certificate
            $table->text('notes')->nullable();
            $table->json('tags')->nullable(); // Searchable tags for categorization

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['common_name', 'status']);
            $table->index(['not_valid_after', 'status']); // For expiry monitoring
            $table->index(['auto_renew', 'next_renewal_attempt_at']); // For renewal processing
            $table->index(['ssl_certificate_authority_id', 'status']);
            $table->index(['is_wildcard', 'status']);
            $table->index(['fingerprint_sha256']);
            $table->index(['status', 'created_at']);

            // Unique constraint on active certificates per domain
            $table->unique(['common_name', 'ssl_certificate_authority_id'], 'unique_active_cert_per_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
