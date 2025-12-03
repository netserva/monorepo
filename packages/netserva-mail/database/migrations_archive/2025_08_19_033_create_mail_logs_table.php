<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();

            // Basic log entry (~10 fields total)
            $table->timestamp('timestamp');
            $table->string('level'); // info, warning, error, debug
            $table->text('message');
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->string('message_id')->nullable();
            $table->string('server_component')->nullable(); // postfix, dovecot, etc.

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Essential indexes only
            $table->index(['timestamp', 'level']);
            $table->index(['message_id']);
            $table->index(['sender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
