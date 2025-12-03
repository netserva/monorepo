<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Key filename without extension');
            $table->string('type')->default('ed25519')->comment('Key type: ed25519, rsa, ecdsa');
            $table->integer('key_size')->nullable()->comment('Key size in bits (for RSA)');
            $table->text('public_key')->nullable()->comment('Public key content');
            $table->text('private_key')->nullable()->comment('Private key content (encrypted at rest)');
            $table->string('fingerprint')->nullable()->comment('SHA256 fingerprint');
            $table->string('comment')->nullable()->comment('Key comment (usually user@host)');
            $table->boolean('has_passphrase')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('type');
        });

        // Update ssh_hosts to reference ssh_keys by name
        // The identity_file column stores the key name, not full path
        // Full path is always ~/.ssh/keys/{name}
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_keys');
    }
};
