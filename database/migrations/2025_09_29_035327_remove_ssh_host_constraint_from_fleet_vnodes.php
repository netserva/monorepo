<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            // Drop the foreign key constraint to non-existent ssh_hosts table
            $table->dropForeign(['ssh_host_id']);

            // Keep the column but make it nullable without constraint
            // This allows VNodes to reference SSH configurations by name instead
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            // Restore the foreign key constraint (if ssh_hosts table exists)
            $table->foreignId('ssh_host_id')->nullable()->constrained('ssh_hosts')->onDelete('set null')->change();
        });
    }
};
