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
        Schema::create('sw_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique()->index();
            $table->string('domain_status')->nullable();
            $table->timestamp('domain_expiry')->nullable();
            $table->timestamp('domain_registered')->nullable();
            $table->string('registrant')->nullable();
            $table->json('nameservers')->nullable();
            $table->string('dns_config_type')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->boolean('id_protect')->default(false);
            $table->json('contacts')->nullable(); // registrant, tech, admin, billing
            $table->json('raw_response')->nullable(); // Full API response for reference
            $table->boolean('is_active')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('domain_expiry');
            $table->index('is_active');
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sw_domains');
    }
};
