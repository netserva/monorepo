<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NS 1.0 to NS 3.0 Database Migration for mrn (mail.renta.net)
 *
 * This migration:
 * 1. Renames sysadm → sysadm_bkp
 * 2. Creates new sysadm database with NS 3.0 schema
 * 3. Migrates data from sysadm_bkp to sysadm with column transformations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->info('Starting NS 1.0 → NS 3.0 database migration...');

        // Step 1: Backup existing database
        $this->info('Step 1: Backing up sysadm → sysadm_bkp');
        DB::statement('CREATE DATABASE IF NOT EXISTS sysadm_bkp');
        DB::statement('DROP DATABASE IF EXISTS sysadm_bkp');
        DB::statement('CREATE DATABASE sysadm_bkp');

        // Clone all tables to backup
        $tables = DB::select('SHOW TABLES FROM sysadm');
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            DB::statement("CREATE TABLE sysadm_bkp.{$tableName} LIKE sysadm.{$tableName}");
            DB::statement("INSERT INTO sysadm_bkp.{$tableName} SELECT * FROM sysadm.{$tableName}");
        }

        $this->info('Step 2: Dropping old sysadm database');
        DB::statement('DROP DATABASE sysadm');

        $this->info('Step 3: Creating new sysadm database with NS 3.0 schema');
        DB::statement('CREATE DATABASE sysadm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        DB::connection()->setDatabaseName('sysadm');

        // Create vhosts table (NS 3.0 schema)
        Schema::create('vhosts', function ($table) {
            $table->id();
            $table->text('domain')->unique();
            $table->integer('uid');
            $table->integer('gid');
            $table->integer('active')->default(1);
            $table->timestamps();

            $table->index('domain', 'idx_vhosts_domain');
        });

        // Create vmails table (NS 3.0 schema)
        Schema::create('vmails', function ($table) {
            $table->id();
            $table->string('user', 255)->unique();
            $table->string('pass', 255);
            $table->string('home', 255);
            $table->integer('uid');
            $table->integer('gid');
            $table->integer('active')->default(1);
            $table->timestamps();

            $table->index('user', 'idx_vmails_user');
        });

        // Create valias table (NS 3.0 schema)
        Schema::create('valias', function ($table) {
            $table->id();
            $table->string('source', 255)->unique();
            $table->text('target');
            $table->integer('active')->default(1);
            $table->timestamps();

            $table->index('source', 'idx_valias_source');
        });

        $this->info('Step 4: Migrating data from sysadm_bkp to sysadm...');

        // Migrate vhosts
        $this->info('Migrating vhosts...');
        DB::statement("
            INSERT INTO sysadm.vhosts (id, domain, uid, gid, active, created_at, updated_at)
            SELECT
                id,
                domain,
                uid,
                gid,
                active,
                created as created_at,
                updated as updated_at
            FROM sysadm_bkp.vhosts
        ");

        // Migrate vmails - NOTE: home paths will be updated by filesystem migration script
        $this->info('Migrating vmails (paths will be updated separately)...');
        DB::statement("
            INSERT INTO sysadm.vmails (id, user, pass, home, uid, gid, active, created_at, updated_at)
            SELECT
                id,
                user,
                password as pass,
                REPLACE(home, '/home/u/', '/srv/') as home,
                uid,
                gid,
                active,
                created as created_at,
                updated as updated_at
            FROM sysadm_bkp.vmails
        ");

        // Migrate valias
        $this->info('Migrating valias...');
        DB::statement("
            INSERT INTO sysadm.valias (id, source, target, active, created_at, updated_at)
            SELECT
                id,
                source,
                target,
                active,
                created as created_at,
                updated as updated_at
            FROM sysadm_bkp.valias
        ");

        // Update vmails home paths to new structure
        $this->info('Updating vmails home paths to NS 3.0 structure...');
        DB::statement("
            UPDATE sysadm.vmails
            SET home = CONCAT(
                '/srv/',
                SUBSTRING_INDEX(user, '@', -1),
                '/msg/',
                SUBSTRING_INDEX(user, '@', 1)
            )
        ");

        $this->info('Migration statistics:');
        $this->info('  Vhosts migrated: ' . DB::table('sysadm.vhosts')->count());
        $this->info('  Vmails migrated: ' . DB::table('sysadm.vmails')->count());
        $this->info('  Valias migrated: ' . DB::table('sysadm.valias')->count());

        $this->info('Database migration completed successfully!');
        $this->info('Backup database: sysadm_bkp (keep for 7 days)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->info('Rolling back: Restoring sysadm from sysadm_bkp...');

        if (!DB::statement('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = "sysadm_bkp"')) {
            $this->error('Backup database sysadm_bkp not found! Cannot rollback.');
            return;
        }

        DB::statement('DROP DATABASE IF EXISTS sysadm');
        DB::statement('CREATE DATABASE sysadm');

        $tables = DB::select('SHOW TABLES FROM sysadm_bkp');
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            DB::statement("CREATE TABLE sysadm.{$tableName} LIKE sysadm_bkp.{$tableName}");
            DB::statement("INSERT INTO sysadm.{$tableName} SELECT * FROM sysadm_bkp.{$tableName}");
        }

        $this->info('Rollback completed: sysadm restored from sysadm_bkp');
    }

    /**
     * Helper method to output info
     */
    protected function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    /**
     * Helper method to output error
     */
    protected function error(string $message): void
    {
        echo "[ERROR] {$message}\n";
    }
};
