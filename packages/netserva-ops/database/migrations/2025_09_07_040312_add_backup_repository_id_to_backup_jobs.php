<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // This migration is redundant - backup_repository_id already exists in the original create_backup_jobs_table migration
        // Skip this migration to avoid conflicts during table creation

    }

    public function down(): void
    {
        // This migration is redundant - backup_repository_id is managed by the original create_backup_jobs_table migration
        // Skip rollback to avoid conflicts

    }
};
