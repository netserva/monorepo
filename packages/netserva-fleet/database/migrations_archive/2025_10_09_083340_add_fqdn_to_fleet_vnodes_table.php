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
            $table->string('fqdn')->nullable()->after('name')->comment('Fully Qualified Domain Name of the server');
            $table->index('fqdn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->dropIndex(['fqdn']);
            $table->dropColumn('fqdn');
        });
    }
};
