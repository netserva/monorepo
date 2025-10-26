<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NetServa 3.0 Unified Credential Vault (VPass)
     *
     * Architecture:
     * - ALL sensitive credentials stored on workstation only (encrypted at rest)
     * - Polymorphic ownership: venue/vsite/vnode/vhost
     * - Hierarchical inheritance: vhost → vnode → vsite → venue
     * - Supports: mail passwords, API keys, DB credentials, SSL keys, etc.
     *
     * Security:
     * - NEVER synced to remote servers
     * - Encrypted at rest via Laravel's APP_KEY
     * - Zero cleartext on production servers
     *
     * Naming: "vpass" = Virtual Password (aligns with vhost, vnode, vmail, etc.)
     */
    public function up(): void
    {
        Schema::create('vpass', function (Blueprint $table) {
            $table->id();

            // Polymorphic ownership (venue/vsite/vnode/vhost)
            $table->string('owner_type')->index()
                ->comment('FleetVenue, FleetVSite, FleetVNode, FleetVHost');
            $table->unsignedBigInteger('owner_id')->index();

            // Credential identification (5-char naming where possible)
            $table->string('ptype', 32)->index()
                ->comment('Password type: VMAIL, APKEY, DBPWD, SSLKY, OAUTH, etc.');
            $table->string('pserv', 64)->index()
                ->comment('Service provider: cloudflare, binarylane, proxmox, dovecot, mysql, etc.');
            $table->string('pname', 128)
                ->comment('Identifier: email, key name, username, account ID');

            // Secure storage (encrypted at rest with APP_KEY)
            $table->text('pdata')
                ->comment('Encrypted secret: password, API key, token, private key');
            $table->json('pmeta')->nullable()
                ->comment('Metadata: zone IDs, endpoints, account info (JSON)');

            // Lifecycle management
            $table->boolean('pstat')->default(true)->index()
                ->comment('Active status (1=active, 0=disabled)');
            $table->timestamp('pdate')->nullable()
                ->comment('Last rotated timestamp');
            $table->timestamp('pused')->nullable()
                ->comment('Last used timestamp');
            $table->timestamp('pexpd')->nullable()
                ->comment('Expiration timestamp');
            $table->text('pnote')->nullable()
                ->comment('Admin notes');

            $table->timestamps();

            // Unique constraint: one credential per owner+service+name
            $table->unique(
                ['owner_type', 'owner_id', 'pserv', 'pname'],
                'unique_vpass'
            );

            // Performance indexes
            $table->index(['owner_type', 'owner_id'], 'idx_owner');
            $table->index(['pserv', 'ptype'], 'idx_service');
            $table->index(['pstat', 'pserv'], 'idx_active_service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpass');
    }
};
