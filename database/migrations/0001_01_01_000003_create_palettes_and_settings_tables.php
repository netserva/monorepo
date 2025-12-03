<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Palettes for theming
        Schema::create('palettes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label')->nullable();
            $table->string('group')->default('colors');
            $table->text('description')->nullable();
            $table->json('colors')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });

        // NetServa settings
        Schema::create('netserva_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('category')->default('general');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Installed plugins tracking
        Schema::create('installed_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('plugin_class')->nullable();
            $table->string('version')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->json('settings')->nullable();
            $table->string('package_name')->nullable();
            $table->string('author')->nullable();
            $table->string('source')->default('local');
            $table->string('source_url')->nullable();
            $table->string('category')->nullable();
            $table->json('composer_data')->nullable();
            $table->integer('navigation_sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_plugins');
        Schema::dropIfExists('netserva_settings');
        Schema::dropIfExists('palettes');
    }
};
