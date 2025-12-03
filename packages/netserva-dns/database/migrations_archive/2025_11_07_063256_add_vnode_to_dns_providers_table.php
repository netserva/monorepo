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
        Schema::table('dns_providers', function (Blueprint $table) {
            $table->string('vnode')->nullable()->unique()->after('name');
            $table->index('vnode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dns_providers', function (Blueprint $table) {
            $table->dropIndex(['vnode']);
            $table->dropColumn('vnode');
        });
    }
};
