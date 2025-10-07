<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_queue', function (Blueprint $table) {
            $table->id();

            // Basic identification (~11 fields total)
            $table->string('message_id')->nullable();
            $table->string('sender')->nullable();
            $table->string('recipient');
            $table->string('subject')->nullable();

            // Queue status
            $table->enum('status', ['queued', 'processing', 'sent', 'deferred', 'bounced', 'failed'])
                ->default('queued');
            $table->integer('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();

            // Timestamps - created_at covers queued time
            $table->timestamps();

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            // Essential indexes only
            $table->index(['status', 'next_retry_at']);
            $table->index(['sender']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_queue');
    }
};
