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
        Schema::table('migration_jobs', function (Blueprint $table) {
            // Only add fields that don't exist yet (ssh_host_id already exists)
            if (! Schema::hasColumn('migration_jobs', 'job_name')) {
                $table->string('job_name')->after('id');
            }
            if (! Schema::hasColumn('migration_jobs', 'description')) {
                $table->text('description')->nullable()->after('job_name');
            }
            if (! Schema::hasColumn('migration_jobs', 'dry_run')) {
                $table->boolean('dry_run')->default(false)->after('ssh_host_id');
            }
            if (! Schema::hasColumn('migration_jobs', 'step_backup')) {
                $table->boolean('step_backup')->default(true)->after('dry_run');
            }
            if (! Schema::hasColumn('migration_jobs', 'step_cleanup')) {
                $table->boolean('step_cleanup')->default(false)->after('step_backup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migration_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'job_name',
                'description',
                'ssh_host_id',
                'dry_run',
                'step_backup',
                'step_cleanup',
            ]);
        });
    }
};
