<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();

            // Basic identification (~16 fields total)
            $table->string('email')->unique();
            $table->string('full_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('mail_domain_id')
                ->constrained('mail_domains')
                ->cascadeOnDelete();

            // Authentication
            $table->string('password_hash');
            $table->boolean('is_active')->default(true);

            // Storage and quota
            $table->bigInteger('quota_bytes')->nullable(); // 0 = unlimited
            $table->bigInteger('used_bytes')->default(0);

            // Service permissions
            $table->boolean('enable_imap')->default(true);
            $table->boolean('enable_pop3')->default(false);

            // Forwarding
            $table->string('forward_to')->nullable();

            // Auto-reply
            $table->boolean('auto_reply_enabled')->default(false);
            $table->text('auto_reply_message')->nullable();

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            // Essential indexes only
            $table->index(['mail_domain_id', 'is_active']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
