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
            // Drop foreign key constraints first
            try {
                $table->dropForeign(['infrastructure_node_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }

            try {
                $table->dropForeign(['created_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }

            // Drop indexes that reference columns we're dropping
            try {
                $table->dropIndex(['type', 'environment']);
            } catch (\Exception $e) {
                // Index might not exist
            }
            try {
                $table->dropIndex(['category', 'environment']);
            } catch (\Exception $e) {
                // Index might not exist
            }
        });

        Schema::table('secrets', function (Blueprint $table) {
            // Remove enterprise compliance and management bloat
            $table->dropColumn([
                'access_policy',          // Enterprise policy management
                'compliance_required',    // Enterprise compliance tracking
                'access_count',           // Enterprise usage analytics
                'version',                // Enterprise versioning system
                'rotation_interval',      // Enterprise automated rotation
                'last_rotated_at',       // Enterprise rotation tracking
                'last_accessed_at',      // Enterprise access tracking
                'environment',           // Over-categorization
                'category',              // Redundant with secret_category_id
                'infrastructure_node_id', // Cross-package dependency
                'created_by',            // User tracking bloat
                'updated_by',            // User tracking bloat
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secrets', function (Blueprint $table) {
            // Restore removed columns
            $table->json('access_policy')->nullable()->comment('JSON access control policy');
            $table->boolean('compliance_required')->default(false);
            $table->integer('access_count')->default(0);
            $table->string('version')->default('1.0');
            $table->integer('rotation_interval')->nullable()->comment('Days between rotations');
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('environment')->default('production');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('infrastructure_node_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Restore indexes
            $table->index(['environment']);
            $table->index(['category']);
            $table->index('infrastructure_node_id');
            $table->index('created_by');
        });
    }
};
