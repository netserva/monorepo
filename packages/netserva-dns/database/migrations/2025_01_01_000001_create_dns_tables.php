<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DNS Providers (PowerDNS, Cloudflare, etc.)
        Schema::create('dns_providers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('powerdns');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('connection_config');
            $table->boolean('active')->default(true);
            $table->string('version')->nullable();
            $table->timestamp('last_sync')->nullable();
            $table->json('sync_config')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('rate_limit')->default(100);
            $table->integer('timeout')->default(30);
            $table->string('vnode')->nullable();
            $table->timestamps();

            $table->index(['active', 'type']);
            $table->index('last_sync');
        });

        // DNS Zones
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_provider_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('name');
            $table->enum('kind', ['Primary', 'Secondary', 'Native', 'Forwarded'])->default('Primary');
            $table->json('masters')->nullable();
            $table->unsignedBigInteger('serial')->nullable();
            $table->timestamp('last_check')->nullable();
            $table->unsignedBigInteger('notified_serial')->nullable();
            $table->string('account')->nullable();
            $table->boolean('active')->default(true);
            $table->json('provider_data')->nullable();
            $table->timestamp('last_synced')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('ttl')->default(3600);
            $table->boolean('auto_dnssec')->default(false);
            $table->json('nameservers')->nullable();
            $table->integer('records_count')->default(0);
            $table->boolean('dnssec_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['dns_provider_id', 'name']);
            $table->index(['active', 'kind']);
            $table->index('last_synced');
        });

        // DNS Records
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('type');
            $table->text('content');
            $table->integer('ttl')->default(3600);
            $table->integer('priority')->default(0);
            $table->boolean('disabled')->default(false);
            $table->boolean('auth')->default(true);
            $table->string('ordername')->nullable();
            $table->text('comment')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('last_synced')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dns_zone_id', 'type']);
            $table->index(['disabled', 'auth']);
            $table->index('last_synced');
        });

        // Domain Registrars
        Schema::create('domain_registrars', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('registrar_type');
            $table->string('api_endpoint')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('api_secret_encrypted')->nullable();
            $table->json('additional_config')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Domain Registrations (Synergy Wholesale domains)
        Schema::create('domain_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->foreignId('domain_registrar_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('unknown');
            $table->date('registration_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('registrant_contact')->nullable();
            $table->string('admin_contact')->nullable();
            $table->string('tech_contact')->nullable();
            $table->string('billing_contact')->nullable();
            $table->json('nameservers')->nullable();
            $table->boolean('privacy_enabled')->default(false);
            $table->boolean('lock_enabled')->default(true);
            $table->json('dns_config')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('expiry_date');
        });

        // SW Domains (legacy Synergy Wholesale import)
        Schema::create('sw_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->string('status')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->json('nameservers')->nullable();
            $table->json('contacts')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sw_domains');
        Schema::dropIfExists('domain_registrations');
        Schema::dropIfExists('domain_registrars');
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
        Schema::dropIfExists('dns_providers');
    }
};
