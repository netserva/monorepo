<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_servers', function (Blueprint $table) {
            $table->id();

            // Basic identification (~18 fields total)
            $table->string('name');
            $table->string('hostname');
            $table->text('description')->nullable();
            $table->foreignId('infrastructure_node_id')
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();

            // Server configuration
            $table->enum('server_type', ['postfix_dovecot', 'exim_dovecot', 'sendmail_courier', 'custom'])
                ->default('postfix_dovecot');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->string('public_ip')->nullable();

            // Port configuration
            $table->integer('smtp_port')->default(25);
            $table->integer('imap_port')->default(143);
            $table->integer('pop3_port')->default(110);

            // SSL configuration
            $table->boolean('enable_ssl')->default(true);
            $table->string('ssl_cert_path')->nullable();
            $table->string('ssl_key_path')->nullable();

            // Status and monitoring
            $table->enum('status', ['healthy', 'warning', 'error', 'maintenance', 'unknown'])
                ->default('unknown');

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            // Essential indexes only
            $table->index(['is_active', 'server_type']);
            $table->index(['infrastructure_node_id']);
            $table->index(['hostname']);
            $table->unique(['hostname', 'infrastructure_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_servers');
    }
};
