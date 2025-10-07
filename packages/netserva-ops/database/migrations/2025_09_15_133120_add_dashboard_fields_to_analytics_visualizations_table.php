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
        Schema::table('analytics_visualizations', function (Blueprint $table) {
            $table->foreignId('analytics_dashboard_id')->nullable()->constrained('analytics_dashboards')->nullOnDelete();
            $table->integer('dashboard_position_x')->nullable();
            $table->integer('dashboard_position_y')->nullable();
            $table->integer('dashboard_width')->default(6);
            $table->integer('dashboard_height')->default(4);

            $table->index(['analytics_dashboard_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_visualizations', function (Blueprint $table) {
            $table->dropForeign(['analytics_dashboard_id']);
            $table->dropColumn([
                'analytics_dashboard_id',
                'dashboard_position_x',
                'dashboard_position_y',
                'dashboard_width',
                'dashboard_height',
            ]);
        });
    }
};
