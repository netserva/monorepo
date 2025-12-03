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
        Schema::table('ip_reservations', function (Blueprint $table) {
            $table->dropIndex('ip_reservations_reservation_type_is_active_index');
        });

        Schema::table('ip_reservations', function (Blueprint $table) {
            $table->dropColumn('reservation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_reservations', function (Blueprint $table) {
            $table->string('reservation_type')->default('static_range')->after('description');
        });
    }
};
