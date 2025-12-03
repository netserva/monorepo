<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NetServa 3.0 Security Architecture:
     * - Cleartext passwords stored ONLY on workstation (encrypted at rest)
     * - Remote servers store SHA512-CRYPT hashes only (Dovecot compatible)
     * - Supports password rotation, hints, audit trail
     */
    public function up(): void
    {
        Schema::create('mail_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vhost_id')->constrained('fleet_vhosts')->onDelete('cascade');
            $table->string('email')->unique()->index()
                ->comment('Email address (user@domain.com)');
            $table->text('cleartext_password')
                ->comment('Cleartext password - encrypted at rest via Laravel encrypted casting');
            $table->string('password_hint')->nullable()
                ->comment('Optional hint for password recovery');
            $table->text('notes')->nullable()
                ->comment('Admin notes, creation context');
            $table->boolean('is_active')->default(true)
                ->comment('Enable/disable mailbox without deleting');
            $table->timestamp('last_rotated_at')->nullable()
                ->comment('Password rotation tracking');
            $table->timestamps();

            // Indexes for common queries
            $table->index('fleet_vhost_id');
            $table->index(['is_active', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_credentials');
    }
};
