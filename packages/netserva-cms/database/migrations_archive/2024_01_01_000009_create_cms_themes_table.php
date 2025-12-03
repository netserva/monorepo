<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Theme identifier (slug)');
            $table->string('display_name')->comment('Human-readable theme name');
            $table->text('description')->nullable()->comment('Theme description');
            $table->string('version')->default('1.0.0')->comment('Theme version (semver)');
            $table->string('author')->nullable()->comment('Theme author name');
            $table->string('parent_theme')->nullable()->comment('Parent theme name for child themes');
            $table->boolean('is_active')->default(false)->comment('Whether this theme is currently active');
            $table->json('manifest')->nullable()->comment('Parsed theme.json contents');
            $table->timestamps();

            $table->index('is_active');
            $table->index('parent_theme');

            $table->foreign('parent_theme')
                ->references('name')
                ->on('cms_themes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_themes');
    }
};
