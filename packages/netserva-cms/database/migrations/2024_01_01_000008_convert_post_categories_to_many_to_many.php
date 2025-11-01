<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts Post categories from belongsTo (single category_id) to
     * belongsToMany (pivot table) relationship.
     */
    public function up(): void
    {
        // Create pivot table
        Schema::create('cms_category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('cms_categories')->onDelete('cascade');
            $table->foreignId('post_id')->constrained('cms_posts')->onDelete('cascade');
            $table->primary(['category_id', 'post_id']);
            $table->timestamps();
        });

        // Only migrate data if category_id column exists (for upgrading existing installations)
        if (Schema::hasColumn('cms_posts', 'category_id')) {
            // Migrate existing data from posts.category_id to pivot table
            DB::statement('
                INSERT INTO cms_category_post (category_id, post_id, created_at, updated_at)
                SELECT category_id, id, created_at, updated_at
                FROM cms_posts
                WHERE category_id IS NOT NULL
            ');

            // Drop category_id column from posts table
            Schema::table('cms_posts', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add category_id column to posts
        Schema::table('cms_posts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->onDelete('set null');
        });

        // Migrate data back (use first category from pivot if post has multiple)
        DB::statement('
            UPDATE cms_posts p
            SET category_id = (
                SELECT category_id
                FROM cms_category_post cp
                WHERE cp.post_id = p.id
                LIMIT 1
            )
        ');

        // Drop pivot table
        Schema::dropIfExists('cms_category_post');
    }
};
