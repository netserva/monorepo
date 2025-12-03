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
        Schema::table('installed_plugins', function (Blueprint $table) {
            $table->integer('navigation_sort')->default(99)->after('is_enabled');
            $table->string('navigation_group')->nullable()->after('navigation_sort');
            $table->string('navigation_icon')->nullable()->after('navigation_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installed_plugins', function (Blueprint $table) {
            $table->dropColumn(['navigation_sort', 'navigation_group', 'navigation_icon']);
        });
    }
};
