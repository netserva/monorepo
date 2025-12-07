<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if already migrated (table renamed)
        if (Schema::hasTable('crm_clients')) {
            return;
        }

        // Only proceed if old table exists
        if (! Schema::hasTable('crm_customers')) {
            // Create fresh table with new schema
            Schema::create('crm_clients', function (Blueprint $table) {
                $table->id();

                // Core identification - unified client (no company/individual distinction)
                $table->string('name');  // Display name (auto-generated from first/last or company)
                $table->string('slug')->unique();
                $table->enum('status', ['active', 'prospect', 'suspended', 'cancelled'])->default('active');

                // Personal details (always available)
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();

                // Business details (optional - makes it a business client)
                $table->string('company_name')->nullable();
                $table->string('abn', 14)->nullable();  // Australian Business Number
                $table->string('acn', 11)->nullable();  // Australian Company Number

                // Contact - unified phone fields
                $table->string('email');
                $table->string('home_phone', 20)->nullable();
                $table->string('work_phone', 20)->nullable();

                // Address
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('state', 50)->nullable();
                $table->string('postcode', 20)->nullable();
                $table->string('country', 2)->default('AU');

                // Metadata
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->string('external_id')->nullable();

                // Timestamps
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index('email');
                $table->index('status');
                $table->index('external_id');
                $table->index('abn');
                $table->index('company_name');
            });

            return;
        }

        // Migrate existing data: rename columns and table
        Schema::table('crm_customers', function (Blueprint $table) {
            // Rename phone -> home_phone
            $table->renameColumn('phone', 'home_phone');
            // Rename mobile -> work_phone
            $table->renameColumn('mobile', 'work_phone');
        });

        // Drop the type column and its index
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['status', 'type']);
            $table->dropColumn('type');
        });

        // Rename table
        Schema::rename('crm_customers', 'crm_clients');
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_clients')) {
            return;
        }

        // Rename table back
        Schema::rename('crm_clients', 'crm_customers');

        // Add type column back
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->enum('type', ['company', 'individual'])->default('company')->after('slug');
            $table->index('type');
            $table->index(['status', 'type']);
        });

        // Rename columns back
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->renameColumn('home_phone', 'phone');
            $table->renameColumn('work_phone', 'mobile');
        });
    }
};
