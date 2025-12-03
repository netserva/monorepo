<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_theme_id')
                ->constrained('cms_themes')
                ->cascadeOnDelete()
                ->comment('Theme this setting belongs to');
            $table->string('key')->comment('Setting key (e.g., colors.primary)');
            $table->text('value')->comment('Setting value');
            $table->string('type')->default('string')->comment('Value type: string, boolean, integer, color, json');
            $table->string('category')->default('general')->comment('Setting category: colors, typography, layout, etc.');
            $table->timestamps();

            $table->unique(['cms_theme_id', 'key']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_theme_settings');
    }
};
