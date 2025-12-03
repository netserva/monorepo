<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mail Servers
        Schema::create('mail_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('infrastructure_node_id');
            $table->enum('server_type', ['postfix_dovecot', 'exim_dovecot', 'sendmail_courier', 'custom'])->default('postfix_dovecot');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->string('public_ip')->nullable();
            $table->integer('smtp_port')->default(25);
            $table->integer('imap_port')->default(143);
            $table->integer('pop3_port')->default(110);
            $table->boolean('enable_ssl')->default(true);
            $table->string('ssl_cert_path')->nullable();
            $table->string('ssl_key_path')->nullable();
            $table->enum('status', ['healthy', 'warning', 'error', 'maintenance', 'unknown'])->default('unknown');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['hostname', 'infrastructure_node_id']);
            $table->index(['is_active', 'server_type']);
            $table->index('infrastructure_node_id');
            $table->index('hostname');
        });

        // Mail Domains
        Schema::create('mail_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->foreignId('mail_server_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('enable_dkim')->default(true);
            $table->boolean('enable_spf')->default(true);
            $table->boolean('enable_dmarc')->default(true);
            $table->boolean('relay_enabled')->default(false);
            $table->string('relay_host')->nullable();
            $table->integer('relay_port')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['mail_server_id', 'is_active']);
            $table->index('domain');
        });

        // Mailboxes
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('full_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('mail_domain_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->boolean('enable_imap')->default(true);
            $table->boolean('enable_pop3')->default(false);
            $table->string('forward_to')->nullable();
            $table->boolean('auto_reply_enabled')->default(false);
            $table->text('auto_reply_message')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['mail_domain_id', 'is_active']);
            $table->index('email');
        });

        // Mail Aliases
        Schema::create('mail_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias_email')->unique();
            $table->json('destination_emails');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['alias_email', 'is_active']);
        });

        // Mail Credentials (for remote discovery)
        Schema::create('mail_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vnode_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain');
            $table->string('email');
            $table->text('password');
            $table->string('maildir')->nullable();
            $table->integer('uid')->nullable();
            $table->integer('gid')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fleet_vnode_id', 'email']);
        });

        // Mail Queue
        Schema::create('mail_queue', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable();
            $table->string('sender')->nullable();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->enum('status', ['queued', 'processing', 'sent', 'deferred', 'bounced', 'failed'])->default('queued');
            $table->integer('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index('sender');
            $table->index('created_at');
        });

        // Mail Logs
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->string('level');
            $table->text('message');
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->string('message_id')->nullable();
            $table->string('server_component')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['timestamp', 'level']);
            $table->index('message_id');
            $table->index('sender');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('mail_queue');
        Schema::dropIfExists('mail_credentials');
        Schema::dropIfExists('mail_aliases');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('mail_domains');
        Schema::dropIfExists('mail_servers');
    }
};
