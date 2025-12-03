<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fleet Venues (data centers / locations)
        Schema::create('fleet_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('provider')->nullable();
            $table->string('region')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();
            $table->timestamps();
        });

        // Fleet VSites (logical groupings / sites)
        Schema::create('fleet_vsites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('fleet_venue_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->string('dns_provider')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();
            $table->timestamps();
        });

        // Fleet VNodes (servers / virtual machines)
        Schema::create('fleet_vnodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('fqdn')->nullable();
            $table->foreignId('fleet_vsite_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('ipv6_address')->nullable();
            $table->string('os_type')->nullable();
            $table->string('os_version')->nullable();
            $table->string('status')->default('unknown');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_mail_enabled')->default(false);
            $table->string('dns_provider')->nullable();
            $table->string('database_type')->default('mysql');
            $table->string('mail_db_host')->nullable();
            $table->string('mail_db_name')->nullable();
            $table->string('mail_db_user')->nullable();
            $table->string('mail_db_pass')->nullable();
            // BinaryLane fields
            $table->unsignedBigInteger('binarylane_id')->nullable();
            $table->string('binarylane_region')->nullable();
            $table->string('binarylane_size')->nullable();
            $table->string('binarylane_image')->nullable();
            $table->string('binarylane_status')->nullable();
            $table->unsignedBigInteger('binarylane_vpc_id')->nullable();
            $table->timestamp('binarylane_created_at')->nullable();
            $table->json('binarylane_data')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();
            $table->timestamps();

            $table->index(['fleet_vsite_id', 'status']);
            $table->index('binarylane_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vnodes');
        Schema::dropIfExists('fleet_vsites');
        Schema::dropIfExists('fleet_venues');
    }
};
