<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet vhosts, vpass, and related tables
 *
 * Note: vconfs table removed - paths are now derived via VhostPathService
 * and credentials are stored in vpass table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fleet VHosts (virtual hosts / websites)
        Schema::create('fleet_vhosts', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->foreignId('vnode_id')->constrained('fleet_vnodes')->cascadeOnDelete();

            // Unix user/group
            $table->unsignedSmallInteger('uid')->default(1000);
            $table->unsignedSmallInteger('gid')->default(1000);
            $table->string('unix_username')->nullable();  // e.g., "u1078" - derived from uid if null

            // Web configuration
            $table->string('document_root')->nullable();  // /srv/domain.com/web (derived if null)
            $table->string('php_version')->default('8.4');
            $table->boolean('ssl_enabled')->default(true);
            $table->string('ssl_type')->default('letsencrypt');  // letsencrypt, self-signed, custom, none

            // Domain classification
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_mail_domain')->default(false);

            // Application info
            $table->string('app_type')->nullable();  // wordpress, laravel, static, custom
            $table->string('app_version')->nullable();
            $table->string('cms_admin_user')->nullable();  // WordPress/CMS admin username

            // Database (name/user only - passwords in vpass)
            $table->string('db_name')->nullable();
            $table->string('db_user')->nullable();

            // Contact
            $table->string('admin_email')->nullable();

            // Status and metadata
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->string('dns_provider')->nullable();
            $table->json('metadata')->nullable();  // For rare edge cases

            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vnode_id', 'status']);
            $table->index('app_type');
        });

        // VPass (credentials vault - can link to vsite, vnode, or vhost)
        Schema::create('vpass', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // Identifier (e.g., "primary", "admin", email address)
            $table->string('service');  // mysql, ssh, sftp, mail, wordpress, api, etc.
            $table->string('username')->nullable();
            $table->text('password');  // Encrypted via cast
            $table->string('url')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->text('notes')->nullable();

            // Link to any level in hierarchy (nullable FKs)
            $table->foreignId('fleet_vsite_id')->nullable()->constrained('fleet_vsites')->nullOnDelete();
            $table->foreignId('fleet_vnode_id')->nullable()->constrained('fleet_vnodes')->nullOnDelete();
            $table->foreignId('fleet_vhost_id')->nullable()->constrained('fleet_vhosts')->nullOnDelete();

            $table->timestamps();

            $table->index(['service', 'name']);
            $table->index('fleet_vsite_id');
            $table->index('fleet_vnode_id');
            $table->index('fleet_vhost_id');
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
        Schema::dropIfExists('fleet_vhosts');
    }
};
