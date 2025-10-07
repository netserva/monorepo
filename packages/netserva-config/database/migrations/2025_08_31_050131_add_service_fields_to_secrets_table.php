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
        Schema::table('secrets', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('access_count');
            $table->integer('rotation_interval')->nullable()->after('version'); // Days
            $table->timestamp('last_rotated_at')->nullable()->after('last_accessed_at');
            $table->json('access_policy')->nullable()->after('metadata'); // Access control policies
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secrets', function (Blueprint $table) {
            $table->dropColumn(['version', 'rotation_interval', 'last_rotated_at', 'access_policy']);
        });
    }
};
