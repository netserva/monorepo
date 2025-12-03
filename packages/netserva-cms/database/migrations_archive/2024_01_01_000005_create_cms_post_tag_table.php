<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('cms_posts')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('cms_tags')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['post_id', 'tag_id']);
            $table->index('post_id');
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_tag');
    }
};
