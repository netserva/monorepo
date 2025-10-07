<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check and remove only columns that actually exist

        // Remove enterprise fields from databases table (if they exist)
        if (Schema::hasTable('databases')) {
            // First check and drop enterprise index if it exists
            try {
                $indexes = DB::select('PRAGMA index_list(databases)');
                foreach ($indexes as $index) {
                    if ($index->name === 'databases_backup_enabled_index') {
                        DB::statement('DROP INDEX databases_backup_enabled_index');
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Index might not exist or already dropped, continue
            }

            $columnsToRemove = [];
            if (Schema::hasColumn('databases', 'size_bytes')) {
                $columnsToRemove[] = 'size_bytes';
            }
            if (Schema::hasColumn('databases', 'backup_enabled')) {
                $columnsToRemove[] = 'backup_enabled';
            }
            if (Schema::hasColumn('databases', 'backup_schedule')) {
                $columnsToRemove[] = 'backup_schedule';
            }

            if (! empty($columnsToRemove)) {
                Schema::table('databases', function (Blueprint $table) use ($columnsToRemove) {
                    $table->dropColumn($columnsToRemove);
                });
            }
        }

        // Remove enterprise fields from database_credentials table (if they exist)
        if (Schema::hasTable('database_credentials')) {
            // First check and drop enterprise index if it exists
            try {
                $indexes = DB::select('PRAGMA index_list(database_credentials)');
                foreach ($indexes as $index) {
                    if ($index->name === 'database_credentials_database_id_username_host_unique') {
                        DB::statement('DROP INDEX database_credentials_database_id_username_host_unique');
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Index might not exist or already dropped, continue
            }

            $columnsToRemove = [];
            if (Schema::hasColumn('database_credentials', 'host')) {
                $columnsToRemove[] = 'host';
            }
            if (Schema::hasColumn('database_credentials', 'privileges')) {
                $columnsToRemove[] = 'privileges';
            }

            if (! empty($columnsToRemove)) {
                Schema::table('database_credentials', function (Blueprint $table) use ($columnsToRemove) {
                    $table->dropColumn($columnsToRemove);
                });
            }
        }
    }

    public function down(): void
    {
        // Re-add enterprise fields to databases table
        Schema::table('databases', function (Blueprint $table) {
            $table->bigInteger('size_bytes')->default(0);
            $table->boolean('backup_enabled')->default(true);
            $table->string('backup_schedule')->default('0 2 * * *');
            $table->index(['backup_enabled']);
        });

        // Re-add enterprise fields to database_credentials table
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->string('host')->default('localhost');
            $table->json('privileges')->default('[]');
        });
    }
};
