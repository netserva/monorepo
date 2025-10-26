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
        Schema::table('glue_records', function (Blueprint $table) {
            $table->renameColumn('is_ignored', 'is_stale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('glue_records', function (Blueprint $table) {
            $table->renameColumn('is_stale', 'is_ignored');
        });
    }
};
