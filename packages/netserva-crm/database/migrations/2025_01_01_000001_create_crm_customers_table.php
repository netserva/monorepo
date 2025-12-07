<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: only create if table doesn't exist
        if (Schema::hasTable('crm_customers')) {
            return;
        }

        Schema::create('crm_customers', function (Blueprint $table) {
            $table->id();

            // Core identification
            $table->string('name');  // Display name (company name or full name)
            $table->string('slug')->unique();
            $table->enum('type', ['company', 'individual'])->default('company');
            $table->enum('status', ['active', 'prospect', 'suspended', 'cancelled'])->default('active');

            // Company fields (nullable for individuals)
            $table->string('company_name')->nullable();
            $table->string('abn', 14)->nullable();  // Australian Business Number (11 digits + spaces)
            $table->string('acn', 11)->nullable();  // Australian Company Number (9 digits + spaces)

            // Individual fields (nullable for companies)
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            // Primary contact (MVP: single contact per customer)
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 2)->default('AU');  // ISO 3166-1 alpha-2

            // Metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();  // Future billing integration, custom fields
            $table->string('external_id')->nullable();  // Link to external systems (WHMCS, etc)

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('email');
            $table->index('status');
            $table->index('type');
            $table->index(['status', 'type']);
            $table->index('external_id');
            $table->index('abn');
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customers');
    }
};
