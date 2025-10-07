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
        Schema::table('secret_categories', function (Blueprint $table) {
            $table->string('status')->default('active')->after('icon');
            $table->integer('sort_order')->default(0)->after('status');
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secret_categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn(['status', 'sort_order']);
        });
    }
};
