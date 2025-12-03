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
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->string('database_type')->default('sqlite')->after('dns_provider_id')
                ->comment('Database type for vhost management: sqlite or mysql (mariadb)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->dropColumn('database_type');
        });
    }
};
