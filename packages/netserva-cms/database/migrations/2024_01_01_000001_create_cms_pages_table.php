<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('template')->default('default');
            $table->foreignId('parent_id')->nullable()->constrained('cms_pages')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable(); // SEO: title, description, keywords, og_image
            $table->json('settings')->nullable(); // Page-specific settings
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('is_published');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
