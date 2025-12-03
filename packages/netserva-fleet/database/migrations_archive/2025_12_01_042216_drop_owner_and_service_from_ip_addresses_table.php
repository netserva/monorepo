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
        // Drop service column if it exists (index is dropped automatically with column in SQLite)
        if (Schema::hasColumn('ip_addresses', 'service')) {
            Schema::table('ip_addresses', function (Blueprint $table) {
                $table->dropColumn('service');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->string('service')->nullable()->after('description');
            $table->index('service');
        });
    }
};
