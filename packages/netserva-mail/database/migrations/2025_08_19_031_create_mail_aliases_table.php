<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_aliases', function (Blueprint $table) {
            $table->id();

            // Basic identification (~8 fields total)
            $table->string('alias_email'); // Full alias email address
            $table->json('destination_emails'); // Array of destination email addresses
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            // Essential indexes only
            $table->index(['alias_email', 'is_active']);
            $table->unique(['alias_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_aliases');
    }
};
