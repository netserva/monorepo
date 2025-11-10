<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Page;
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;
use ZipArchive;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up test storage disk
    Storage::fake('public');
});

describe('cms:reset command', function () {
    it('clears all CMS content with force flag', function () {
        // Create test data
        Post::factory()->count(3)->create();
        Page::factory()->count(2)->create();
        Category::factory()->count(2)->create();
        Tag::factory()->count(3)->create();

        $postCount = Post::count();
        $pageCount = Page::count();
        $categoryCount = Category::count();
        $tagCount = Tag::count();

        expect($postCount)->toBeGreaterThanOrEqual(3);
        expect($pageCount)->toBeGreaterThanOrEqual(2);
        expect($categoryCount)->toBeGreaterThanOrEqual(2);
        expect($tagCount)->toBeGreaterThanOrEqual(3);

        // Run reset command
        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        // Verify all data is cleared
        expect(Post::count())->toBe(0);
        expect(Page::count())->toBe(0);
        expect(Category::count())->toBe(0);
        expect(Tag::count())->toBe(0);
    });

    it('preserves non-CMS data', function () {
        // Create test data
        Post::factory()->count(3)->create();

        // Create non-CMS data (users should remain)
        \App\Models\User::factory()->create();

        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        expect(Post::count())->toBe(0);
        expect(\App\Models\User::count())->toBe(1);
    });
});

describe('cms:export command', function () {
    it('exports CMS content to a ZIP file', function () {
        // Create test data
        Post::factory()->published()->count(3)->create();
        Page::factory()->published()->count(2)->create();
        Category::factory()->count(2)->create();
        Tag::factory()->count(3)->create();

        $outputPath = storage_path('app/test-export.zip');

        // Run export
        $this->artisan('cms:export', [
            '--output' => $outputPath,
        ])->assertSuccessful();

        // Verify export file exists
        expect(File::exists($outputPath))->toBeTrue();
        expect(File::size($outputPath))->toBeGreaterThan(0);

        // Cleanup
        File::delete($outputPath);
    });

    it('includes drafts when specified', function () {
        // Create published and draft posts
        Post::factory()->published()->count(2)->create();
        Post::factory()->unpublished()->count(1)->create();

        $outputPath = storage_path('app/test-export-drafts.zip');

        $this->artisan('cms:export', [
            '--output' => $outputPath,
            '--include-drafts' => true,
        ])->assertSuccessful();

        expect(File::exists($outputPath))->toBeTrue();

        // Cleanup
        File::delete($outputPath);
    });

    it('creates default filename when no output specified', function () {
        Post::factory()->published()->count(1)->create();

        $this->artisan('cms:export')
            ->assertSuccessful();

        // Check that a file was created in storage/app
        $files = File::glob(storage_path('app/cms-export-*.zip'));
        expect($files)->not->toBeEmpty();

        // Cleanup
        foreach ($files as $file) {
            File::delete($file);
        }
    });
});

describe('cms:import command', function () {
    it('imports CMS content from export file', function () {
        // Create categories and tags first
        $categories = Category::factory()->count(2)->create();
        $tags = Tag::factory()->count(3)->create();

        // Create post and attach existing categories
        $originalPost = Post::factory()->published()->create(['title' => 'Test Post']);
        $originalPost->categories()->sync($categories->pluck('id'));
        $originalPost->tags()->sync($tags->pluck('id'));

        // Delete any auto-created categories that aren't attached
        Category::whereDoesntHave('posts')->delete();

        $originalPage = Page::factory()->published()->create(['title' => 'Test Page']);

        $exportPath = storage_path('app/test-import.zip');

        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Clear database
        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        expect(Post::count())->toBe(0);
        expect(Page::count())->toBe(0);

        // Import
        $this->artisan('cms:import', [
            'file' => $exportPath,
            '--conflict-strategy' => 'rename',
            '--force' => true,
        ])->assertSuccessful();

        // Verify imported data
        expect(Post::count())->toBe(1);
        expect(Page::count())->toBe(1);
        expect(Category::count())->toBe(2);
        expect(Tag::count())->toBe(3);

        expect(Post::first()->title)->toBe('Test Post');
        expect(Page::first()->title)->toBe('Test Page');

        // Cleanup
        File::delete($exportPath);
    });

    it('handles slug conflicts with rename strategy', function () {
        // Create and export a post
        $exportPost = Post::factory()->published()->create([
            'title' => 'Exported Post',
            'slug' => 'test-slug',
        ]);

        // Clean up any auto-created categories before export
        Category::whereDoesntHave('posts')->delete();

        $exportPath = storage_path('app/test-conflict.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Delete the post and create a different one with the same slug
        $exportPost->forceDelete();

        // Also delete the orphaned categories
        Category::whereDoesntHave('posts')->delete();

        Post::factory()->published()->create([
            'title' => 'Existing Post',
            'slug' => 'test-slug',
        ]);

        // Import with rename strategy - should rename the imported post
        $this->artisan('cms:import', [
            'file' => $exportPath,
            '--conflict-strategy' => 'rename',
            '--force' => true,
        ])->assertSuccessful();

        // Should have 2 posts with different slugs
        expect(Post::count())->toBe(2);
        expect(Post::where('slug', 'test-slug')->count())->toBe(1);
        expect(Post::where('slug', 'like', 'test-slug-imported%')->count())->toBe(1);

        // Cleanup
        File::delete($exportPath);
    });

    it('validates import file before importing', function () {
        $this->artisan('cms:import', [
            'file' => '/nonexistent/file.zip',
        ])->assertFailed();
    });

    it('supports dry-run mode', function () {
        // Create and export
        Post::factory()->published()->count(2)->create();

        $exportPath = storage_path('app/test-dryrun.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Clear database
        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        expect(Post::count())->toBe(0);

        // Dry-run import
        $this->artisan('cms:import', [
            'file' => $exportPath,
            '--dry-run' => true,
            '--force' => true,
        ])->assertSuccessful();

        // Database should still be empty
        expect(Post::count())->toBe(0);

        // Cleanup
        File::delete($exportPath);
    });
});

describe('export-import round-trip', function () {
    it('preserves data integrity through export and import', function () {
        // Create complex test data
        $category = Category::factory()->create(['name' => 'Tech News']);
        $tag1 = Tag::factory()->create(['name' => 'Laravel']);
        $tag2 = Tag::factory()->create(['name' => 'PHP']);

        $post = Post::factory()->published()->create([
            'title' => 'Laravel Tips',
            'content' => 'Some great Laravel content',
            'excerpt' => 'Tips and tricks',
        ]);

        // Use sync to replace any auto-created categories
        $post->categories()->sync([$category->id]);
        $post->tags()->sync([$tag1->id, $tag2->id]);

        $page = Page::factory()->published()->create([
            'title' => 'About Us',
            'content' => 'About our company',
        ]);

        // Export
        $exportPath = storage_path('app/test-roundtrip.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Clear database
        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        // Import
        $this->artisan('cms:import', [
            'file' => $exportPath,
            '--conflict-strategy' => 'rename',
            '--force' => true,
        ])->assertSuccessful();

        // Verify data
        $importedPost = Post::first();
        expect($importedPost->title)->toBe('Laravel Tips');
        expect($importedPost->content)->toBe('Some great Laravel content');
        expect($importedPost->excerpt)->toBe('Tips and tricks');
        expect($importedPost->categories)->toHaveCount(1);
        expect($importedPost->tags)->toHaveCount(2);
        expect($importedPost->categories->first()->name)->toBe('Tech News');
        expect($importedPost->tags->pluck('name')->toArray())->toContain('Laravel', 'PHP');

        $importedPage = Page::first();
        expect($importedPage->title)->toBe('About Us');
        expect($importedPage->content)->toBe('About our company');

        // Cleanup
        File::delete($exportPath);
    });

    it('preserves hierarchical page structure', function () {
        // Create parent and child pages
        $parent = Page::factory()->published()->create([
            'title' => 'Services',
            'slug' => 'services',
        ]);

        $child1 = Page::factory()->published()->create([
            'title' => 'Web Development',
            'slug' => 'web-development',
            'parent_id' => $parent->id,
        ]);

        $child2 = Page::factory()->published()->create([
            'title' => 'Mobile Apps',
            'slug' => 'mobile-apps',
            'parent_id' => $parent->id,
        ]);

        // Export
        $exportPath = storage_path('app/test-hierarchy.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Clear and import
        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        $this->artisan('cms:import', [
            'file' => $exportPath,
            '--conflict-strategy' => 'rename',
            '--force' => true,
        ])->assertSuccessful();

        // Verify hierarchy
        $importedParent = Page::where('slug', 'services')->first();
        expect($importedParent)->not->toBeNull();
        expect($importedParent->children)->toHaveCount(2);

        $importedChild1 = Page::where('slug', 'web-development')->first();
        expect($importedChild1->parent_id)->toBe($importedParent->id);

        $importedChild2 = Page::where('slug', 'mobile-apps')->first();
        expect($importedChild2->parent_id)->toBe($importedParent->id);

        // Cleanup
        File::delete($exportPath);
    });
});

describe('JSON export format', function () {
    it('exports data as valid JSON', function () {
        Post::factory()->published()->count(2)->create();
        Page::factory()->published()->count(1)->create();

        $exportPath = storage_path('app/test-json-format.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Extract and verify JSON
        $zip = new ZipArchive;
        expect($zip->open($exportPath))->toBeTrue();

        $jsonContent = $zip->getFromName('cms_export.json');
        expect($jsonContent)->not->toBeEmpty();

        $zip->close();

        // Verify it's valid JSON
        $data = json_decode($jsonContent, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($data)->toBeArray();

        // Verify structure
        expect($data)->toHaveKey('manifest');
        expect($data)->toHaveKey('cms_posts');
        expect($data)->toHaveKey('cms_pages');
        expect($data['manifest'])->toHaveKey('export_date');
        expect($data['manifest'])->toHaveKey('stats');

        // Cleanup
        File::delete($exportPath);
    });

    it('handles posts with complex code examples containing special characters', function () {
        // Create a post with complex code that previously failed SQL parsing
        $post = Post::factory()->published()->create([
            'title' => 'Testing with Pest',
            'slug' => 'testing-with-pest',
            'content' => <<<'MARKDOWN'
# Testing with Pest 4.0

Here's how to test Filament resources:

```php
it('validates user input', function () {
    expect($user)
        ->email->toBe('test@example.com')
        ->name->not->toBeEmpty();
});
```

Notice the closing `);` which can confuse parsers.

Another example with multiple special chars:

```php
function test() {
    $data = ['key' => "value's here"];
    return DB::query("SELECT * FROM users WHERE id = '1'");
}
```
MARKDOWN
        ]);

        // Export
        $exportPath = storage_path('app/test-complex-content.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Clear and import
        $this->artisan('cms:reset', ['--force' => true])
            ->assertSuccessful();

        expect(Post::count())->toBe(0);

        $this->artisan('cms:import', [
            'file' => $exportPath,
            '--force' => true,
        ])->assertSuccessful();

        // Verify the post imported correctly with all content intact
        expect(Post::count())->toBe(1);

        $importedPost = Post::first();
        expect($importedPost->title)->toBe('Testing with Pest');
        expect($importedPost->slug)->toBe('testing-with-pest');
        expect($importedPost->content)->toContain('Testing with Pest 4.0');
        expect($importedPost->content)->toContain('});');
        expect($importedPost->content)->toContain("value's here");
        expect($importedPost->content)->toContain('WHERE id = \'1\'');

        // Cleanup
        File::delete($exportPath);
    });

    it('preserves all data types correctly', function () {
        $publishedAt = now()->subDays(5);

        $post = Post::factory()->published()->create([
            'title' => 'Test Post',
            'is_published' => true,
            'published_at' => $publishedAt,
            'meta_title' => 'SEO Title',
        ]);

        $originalWordCount = $post->word_count;

        // Export
        $exportPath = storage_path('app/test-data-types.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        // Extract and check JSON preserves types
        $zip = new ZipArchive;
        $zip->open($exportPath);
        $jsonContent = $zip->getFromName('cms_export.json');
        $zip->close();

        $data = json_decode($jsonContent, true);
        $exportedPost = $data['cms_posts'][0];

        expect($exportedPost['is_published'])->toBe(1); // SQLite stores as integer
        expect($exportedPost['word_count'])->toBe($originalWordCount);
        expect($exportedPost['title'])->toBeString();
        expect($exportedPost['meta_title'])->toBe('SEO Title');

        // Import and verify
        $this->artisan('cms:reset', ['--force' => true])->assertSuccessful();
        $this->artisan('cms:import', ['file' => $exportPath, '--force' => true])
            ->assertSuccessful();

        $importedPost = Post::first();
        expect($importedPost->is_published)->toBeTrue();
        expect($importedPost->word_count)->toBe($originalWordCount);
        expect($importedPost->meta_title)->toBe('SEO Title');

        // Cleanup
        File::delete($exportPath);
    });

    it('handles multi-line content with various newline styles', function () {
        $post = Post::factory()->published()->create([
            'title' => 'Multi-line Test',
            'content' => "Line 1\nLine 2\r\nLine 3\n\nDouble newline\n\n\nTriple newline",
        ]);

        $exportPath = storage_path('app/test-newlines.zip');
        $this->artisan('cms:export', ['--output' => $exportPath])
            ->assertSuccessful();

        $this->artisan('cms:reset', ['--force' => true])->assertSuccessful();
        $this->artisan('cms:import', ['file' => $exportPath, '--force' => true])
            ->assertSuccessful();

        $importedPost = Post::first();
        expect($importedPost->content)->toContain('Line 1');
        expect($importedPost->content)->toContain('Line 2');
        expect($importedPost->content)->toContain('Double newline');

        // Cleanup
        File::delete($exportPath);
    });
});
