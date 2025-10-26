<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add additional fields from SW API that weren't in initial schema
     */
    public function up(): void
    {
        Schema::table('sw_domains', function (Blueprint $table) {
            $table->string('domain_roid')->nullable()->after('domain_name'); // Registry Object ID
            $table->string('domain_password')->nullable()->after('registrant'); // EPP auth code
            $table->string('registry_id')->nullable()->after('domain_roid'); // Registry ID
            $table->timestamp('created_date')->nullable()->after('domain_registered'); // Creation date at registry
            $table->timestamp('icann_verification_date_end')->nullable()->after('created_date');
            $table->string('icann_status')->nullable()->after('icann_verification_date_end');
            $table->boolean('bulk_in_progress')->default(false)->after('is_synced');
            $table->json('ds_data')->nullable()->after('nameservers'); // DNSSEC data
            $table->json('categories')->nullable()->after('dns_config_type'); // SW categories

            $table->index('domain_roid');
            $table->index('registry_id');
            $table->index('created_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sw_domains', function (Blueprint $table) {
            $table->dropIndex(['sw_domains_domain_roid_index']);
            $table->dropIndex(['sw_domains_registry_id_index']);
            $table->dropIndex(['sw_domains_created_date_index']);

            $table->dropColumn([
                'domain_roid',
                'domain_password',
                'registry_id',
                'created_date',
                'icann_verification_date_end',
                'icann_status',
                'bulk_in_progress',
                'ds_data',
                'categories',
            ]);
        });
    }
};
