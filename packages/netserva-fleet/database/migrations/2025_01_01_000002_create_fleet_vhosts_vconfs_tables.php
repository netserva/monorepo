<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fleet VHosts (virtual hosts / websites)
        Schema::create('fleet_vhosts', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->foreignId('fleet_vnode_id')->constrained()->cascadeOnDelete();
            $table->string('document_root')->nullable();
            $table->string('php_version')->default('8.3');
            $table->boolean('ssl_enabled')->default(true);
            $table->string('ssl_type')->default('letsencrypt');
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->string('dns_provider')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('app_type')->nullable();
            $table->string('app_version')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fleet_vnode_id', 'status']);
        });

        // Fleet VHost Credentials
        Schema::create('fleet_vhost_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vhost_id')->constrained('fleet_vhosts')->cascadeOnDelete();
            $table->string('service_type');
            $table->string('account_name');
            $table->string('username')->nullable();
            $table->text('password');
            $table->string('url')->nullable();
            $table->integer('port')->nullable();
            $table->string('path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vhost_id', 'service_type', 'account_name'], 'unique_vhost_service_account');
            $table->index(['vhost_id', 'service_type'], 'idx_vhost_service');
            $table->index(['service_type', 'account_name'], 'idx_service_account');
        });

        // VConfs (vhost configurations stored in database)
        Schema::create('vconfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vhost_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['fleet_vhost_id', 'key']);
        });

        // VPass (password storage)
        Schema::create('vpass', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vhost_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('service');
            $table->string('username');
            $table->text('password');
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fleet_vhost_id', 'service']);
        });

        // Dnsmasq hosts for local DNS resolution
        Schema::create('fleet_dnsmasq_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('hostname');
            $table->string('domain')->nullable();
            $table->foreignId('fleet_vnode_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['ip_address', 'hostname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_dnsmasq_hosts');
        Schema::dropIfExists('vpass');
        Schema::dropIfExists('vconfs');
        Schema::dropIfExists('fleet_vhost_credentials');
        Schema::dropIfExists('fleet_vhosts');
    }
};
