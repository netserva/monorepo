<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Track whether a VNode has valid FCrDNS and can send email.
     * This requires both forward (A) and reverse (PTR) DNS records.
     */
    public function up(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->boolean('email_capable')
                ->default(false)
                ->after('fqdn')
                ->comment('Whether server has valid FCrDNS for email delivery');

            $table->timestamp('fcrdns_validated_at')
                ->nullable()
                ->after('email_capable')
                ->comment('When FCrDNS was last validated');

            $table->index('email_capable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->dropIndex(['email_capable']);
            $table->dropColumn(['email_capable', 'fcrdns_validated_at']);
        });
    }
};
