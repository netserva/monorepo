<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VConfs Table (VHost Configuration Variables)
 *
 * Follows NetServa v-naming: venue → vsite → vnode → vhost → vconf → vserv
 *
 * Dedicated table for NetServa environment variables (up to 60 variables).
 * Each variable is 5 characters uppercase (with optional underscore).
 *
 * Benefits over JSON column:
 * - Queryable individual variables
 * - Indexable for performance
 * - Better for Filament CRUD interface
 * - Type-safe with validation
 * - Easier to add/remove/update single variables
 *
 * Created: 20250107 - Updated: 20250107
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vconfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vhost_id')
                ->constrained('fleet_vhosts')
                ->cascadeOnDelete();

            $table->string('name', 5)->comment('5-char uppercase variable name (e.g., WPATH, U_UID)');
            $table->text('value')->nullable()->comment('Variable value');

            $table->string('category', 20)->nullable()->comment('Variable category (paths, database, etc.)');
            $table->boolean('is_sensitive')->default(false)->comment('Is this a password/secret?');

            $table->timestamps();

            // Unique constraint: one value per variable per vhost
            $table->unique(['fleet_vhost_id', 'name'], 'vhost_vconf_unique');

            // Indexes for performance
            $table->index('fleet_vhost_id');
            $table->index('name');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vconfs');
    }
};
