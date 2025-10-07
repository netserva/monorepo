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
        Schema::table('dns_providers', function (Blueprint $table) {
            $table->integer('rate_limit')->default(100)->after('sync_config');
            $table->integer('timeout')->default(30)->after('rate_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dns_providers', function (Blueprint $table) {
            $table->dropColumn(['rate_limit', 'timeout']);
        });
    }
};
