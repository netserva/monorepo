<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sw_domains', function (Blueprint $table) {
            // Lifecycle status - inspired by WHMCS tbldomains
            $table->enum('lifecycle_status', [
                'pending',
                'pending_registration',
                'pending_transfer',
                'active',
                'grace',
                'redemption',
                'expired',
                'cancelled',
                'transferred_away',
            ])->default('pending')->after('domain_status');

            // Feature flags - track what's enabled per domain
            $table->boolean('dns_management_enabled')->default(false)->after('dns_config_type');
            $table->boolean('email_forwarding_enabled')->default(false)->after('dns_management_enabled');
            $table->boolean('id_protection_enabled')->default(false)->after('email_forwarding_enabled');
            $table->boolean('is_premium')->default(false)->after('id_protection_enabled');

            // Lifecycle management
            $table->boolean('do_not_renew')->default(false)->after('auto_renew');
            $table->boolean('is_synced')->default(false)->after('is_active');
            $table->integer('registration_period_years')->nullable()->after('domain_registered');

            // Grace/redemption tracking
            $table->integer('grace_period_days')->nullable()->after('domain_expiry');
            $table->decimal('grace_period_fee', 10, 2)->nullable()->after('grace_period_days');
            $table->integer('redemption_period_days')->nullable()->after('grace_period_fee');
            $table->decimal('redemption_period_fee', 10, 2)->nullable()->after('redemption_period_days');

            // Add indexes for common queries
            $table->index('lifecycle_status');
            $table->index('is_synced');
            $table->index(['lifecycle_status', 'domain_expiry']); // For expiring domain queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sw_domains', function (Blueprint $table) {
            $table->dropIndex('sw_domains_lifecycle_status_index');
            $table->dropIndex('sw_domains_is_synced_index');
            $table->dropIndex('sw_domains_lifecycle_status_domain_expiry_index');

            $table->dropColumn([
                'lifecycle_status',
                'dns_management_enabled',
                'email_forwarding_enabled',
                'id_protection_enabled',
                'is_premium',
                'do_not_renew',
                'is_synced',
                'registration_period_years',
                'grace_period_days',
                'grace_period_fee',
                'redemption_period_days',
                'redemption_period_fee',
            ]);
        });
    }
};
