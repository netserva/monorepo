<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SSH Hosts (connection configurations)
        Schema::create('ssh_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('host')->unique();
            $table->string('hostname');
            $table->integer('port')->default(22);
            $table->string('user')->default('root');
            $table->string('identity_file')->nullable();
            $table->text('proxy_command')->nullable();
            $table->string('jump_host')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('custom_options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // SSH Keys
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('public_key');
            $table->text('private_key')->nullable();
            $table->string('fingerprint')->nullable();
            $table->string('type')->default('ed25519');
            $table->integer('key_size')->nullable();
            $table->string('comment')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('has_passphrase')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'user_id']);
        });

        // SSH Connections (active/historical connection records)
        Schema::create('ssh_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssh_host_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ssh_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('hostname');
            $table->integer('port')->default(22);
            $table->string('username')->default('root');
            $table->string('connection_type')->default('netserva_managed');
            $table->string('connection_string')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('is_reachable')->nullable();
            $table->json('ssh_options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hostname', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_connections');
        Schema::dropIfExists('ssh_keys');
        Schema::dropIfExists('ssh_hosts');
    }
};
