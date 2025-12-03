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
            $table->string('name');
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('background_color')->nullable();
            $table->string('text_color')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
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
            $table->string('version');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
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
