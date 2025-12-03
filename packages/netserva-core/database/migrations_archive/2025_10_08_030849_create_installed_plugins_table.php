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
        Schema::create('installed_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Plugin ID (e.g., netserva-core)');
            $table->string('plugin_class')->comment('Fully qualified plugin class name');
            $table->string('package_name')->nullable()->comment('Composer package name');
            $table->string('path')->nullable()->comment('Plugin file path');
            $table->string('namespace')->nullable()->comment('Plugin namespace');
            $table->boolean('is_enabled')->default(true)->comment('Plugin enabled status');
            $table->boolean('enabled')->default(true)->comment('Legacy enabled column');
            $table->string('version')->default('1.0.0')->comment('Plugin version (semver)');
            $table->text('description')->nullable()->comment('Plugin description');
            $table->string('author')->nullable()->comment('Plugin author');
            $table->json('config')->nullable()->comment('Plugin configuration');
            $table->json('dependencies')->nullable()->comment('Plugin dependencies');
            $table->string('source')->nullable()->comment('Plugin source (packagist, github, local)');
            $table->string('source_url')->nullable()->comment('Source URL');
            $table->string('installation_method')->nullable()->comment('Installation method (composer, github, manual)');
            $table->string('category')->nullable()->comment('Plugin category');
            $table->json('composer_data')->nullable()->comment('Composer metadata');
            $table->timestamp('installed_at')->nullable()->comment('Installation timestamp');
            $table->timestamp('last_updated_at')->nullable()->comment('Last update timestamp');
            $table->timestamps();

            $table->index('is_enabled');
            $table->index('source');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installed_plugins');
    }
};
