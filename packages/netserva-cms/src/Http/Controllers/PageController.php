<?php

declare(strict_types=1);

namespace NetServa\Cms\Http\Controllers;

use Illuminate\View\View;
use NetServa\Cms\Models\Page;

/**
 * Frontend Page Controller
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class PageController
{
    /**
     * Display the homepage
     *
     * Fallback to Laravel welcome view if no homepage exists (e.g., fresh install)
     */
    public function home(): View
    {
        $page = Page::published()
            ->where('template', 'homepage')
            ->first();

        // Fallback to Laravel welcome page if no homepage exists
        if (! $page) {
            return view('welcome');
        }

        return view('pages.templates.homepage', [
            'page' => $page,
        ]);
    }

    /**
     * Display a page by slug
     */
    public function show(string $slug): View
    {
        $page = Page::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Determine template view based on page template field
        $template = match ($page->template) {
            'homepage' => 'homepage',
            'service' => 'service',
            'pricing' => 'pricing',
            'blank' => 'blank',
            default => 'default',
        };

        return view("pages.templates.{$template}", [
            'page' => $page,
        ]);
    }

    /**
     * Display a nested page by parent slug and page slug
     */
    public function showNested(string $parentSlug, string $slug): View
    {
        // Find parent page
        $parent = Page::published()
            ->where('slug', $parentSlug)
            ->firstOrFail();

        // Find child page
        $page = Page::published()
            ->where('slug', $slug)
            ->where('parent_id', $parent->id)
            ->firstOrFail();

        // Determine template
        $template = match ($page->template) {
            'homepage' => 'homepage',
            'service' => 'service',
            'pricing' => 'pricing',
            'blank' => 'blank',
            default => 'default',
        };

        return view("pages.templates.{$template}", [
            'page' => $page,
            'parent' => $parent,
        ]);
    }
}
