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
            $table->foreignId('palette_id')->nullable()
                ->after('is_active')
                ->constrained('palettes')
                ->nullOnDelete();

            $table->index('palette_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->dropForeign(['palette_id']);
            $table->dropColumn('palette_id');
        });
    }
};
