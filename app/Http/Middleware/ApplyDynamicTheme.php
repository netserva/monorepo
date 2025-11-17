<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NetServa\Cms\Services\ThemeService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply Dynamic Theme Colors to Filament
 *
 * This middleware registers Filament colors dynamically from the CMS theme database.
 * It runs AFTER authentication/database is ready, allowing access to theme settings.
 *
 * Key Benefits:
 * - Filament auto-generates full 11-shade OKLCH palettes from hex values
 * - Single source of truth: CMS database
 * - Works with multi-tenant/user-specific themes
 * - No theme compilation required
 */
class ApplyDynamicTheme
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Only apply theme to Filament admin routes
            if (! $request->is('admin/*') && ! $request->is('admin')) {
                return $next($request);
            }

            // Get active CMS theme
            $themeService = app(ThemeService::class);
            $theme = $themeService->getActive();

            // Register colors with Filament - it will auto-generate full OKLCH palettes
            FilamentColor::register([
                // Map CMS primary → Filament primary
                'primary' => $theme->setting('colors.primary', '#DC2626'),

                // Map CMS accent → Filament info
                'info' => $theme->setting('colors.accent', '#3B82F6'),

                // Keep semantic colors consistent for UX
                'success' => '#10b981', // Green
                'warning' => '#f59e0b', // Amber
                'danger' => '#ef4444',  // Red
                'gray' => '#64748b',    // Slate
            ]);

            Log::debug('Applied dynamic theme colors', [
                'theme' => $theme->name,
                'primary' => $theme->setting('colors.primary'),
                'accent' => $theme->setting('colors.accent'),
            ]);

        } catch (\Exception $e) {
            // Silently fail - don't break admin panel if theme service fails
            Log::warning('Failed to apply dynamic theme colors: '.$e->getMessage());
        }

        return $next($request);
    }
}
