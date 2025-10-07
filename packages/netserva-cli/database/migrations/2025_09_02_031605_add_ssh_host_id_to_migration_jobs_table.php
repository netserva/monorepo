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
        Schema::table('migration_jobs', function (Blueprint $table) {
            $table->foreignId('ssh_host_id')->nullable()->after('target_server')->constrained('ssh_hosts')->nullOnDelete();
            $table->index(['ssh_host_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migration_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ssh_host_id');
        });
    }
};
