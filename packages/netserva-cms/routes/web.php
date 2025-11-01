<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * NetServa CMS Frontend Routes
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */

// Homepage route
if (config('netserva-cms.frontend.enabled', true)) {
    Route::get('/', [\NetServa\Cms\Http\Controllers\PageController::class, 'home'])->name('cms.home');
}

// Blog routes
Route::prefix(config('netserva-cms.blog.route_prefix', 'blog'))->group(function () {
    // Blog index
    Route::get('/', [\NetServa\Cms\Http\Controllers\PostController::class, 'index'])->name('cms.blog.index');

    // Category archive
    Route::get('/category/{slug}', [\NetServa\Cms\Http\Controllers\PostController::class, 'category'])->name('cms.blog.category');

    // Tag archive
    Route::get('/tag/{slug}', [\NetServa\Cms\Http\Controllers\PostController::class, 'tag'])->name('cms.blog.tag');

    // Single post
    Route::get('/{slug}', [\NetServa\Cms\Http\Controllers\PostController::class, 'show'])->name('cms.blog.show');
});

// Page routes (catch-all - should be last)
if (config('netserva-cms.frontend.enabled', true)) {
    // Nested pages (e.g., /services/wordpress-hosting)
    Route::get('/{parentSlug}/{slug}', [\NetServa\Cms\Http\Controllers\PageController::class, 'showNested'])->name('cms.page.nested');

    // Single pages (e.g., /about)
    Route::get('/{slug}', [\NetServa\Cms\Http\Controllers\PageController::class, 'show'])->name('cms.page.show');
}
