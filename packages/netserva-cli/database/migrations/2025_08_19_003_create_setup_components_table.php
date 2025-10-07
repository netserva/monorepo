<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // host, web, db, mail, dns, ssl, etc.
            $table->string('display_name'); // 'Host Setup', 'Web Server', 'Database'
            $table->text('description');
            $table->string('icon')->nullable(); // Heroicon name
            $table->string('category')->default('system'); // system, services, security
            $table->json('dependencies')->nullable(); // Components that must be installed first
            $table->json('configuration_schema'); // JSON schema for configuration options
            $table->json('default_config')->nullable(); // Default values
            $table->text('install_command')->nullable(); // Base command template
            $table->text('verification_command')->nullable(); // Command to verify installation
            $table->text('uninstall_command')->nullable(); // Command to remove component
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false); // Always install this component
            $table->integer('install_order')->default(100); // Order of installation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_components');
    }
};
