<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CMS Pages
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('template')->default('default');
            $table->foreignId('parent_id')->nullable()->constrained('cms_pages')->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('is_published');
            $table->index('published_at');
        });

        // CMS Categories
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('post');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('type');
            $table->index('slug');
        });

        // CMS Tags
        Schema::create('cms_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('slug');
        });

        // CMS Posts
        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_published');
            $table->index('published_at');
            $table->index('author_id');
        });

        // CMS Post-Tag pivot
        Schema::create('cms_post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'tag_id']);
            $table->index('post_id');
            $table->index('tag_id');
        });

        // CMS Category-Post pivot
        Schema::create('cms_category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('cms_categories')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['category_id', 'post_id']);
        });

        // CMS Menus
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location');
            $table->json('items')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('location');
            $table->index('is_active');
        });

        // CMS Themes
        Schema::create('cms_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('author')->nullable();
            $table->string('parent_theme')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('manifest')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('parent_theme');
            $table->foreign('parent_theme')->references('name')->on('cms_themes')->nullOnDelete();
        });

        // CMS Theme Settings
        Schema::create('cms_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_theme_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->string('type')->default('string');
            $table->string('category')->default('general');
            $table->timestamps();

            $table->unique(['cms_theme_id', 'key']);
            $table->index('category');
        });

        // Media (for Spatie Media Library)
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('cms_theme_settings');
        Schema::dropIfExists('cms_themes');
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_category_post');
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_categories');
        Schema::dropIfExists('cms_pages');
    }
};
