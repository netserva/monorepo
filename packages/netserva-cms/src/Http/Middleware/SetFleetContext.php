<?php

declare(strict_types=1);

namespace NetServa\Cms\Http\Middleware;

use App\Services\PaletteResolver;
use Closure;
use Illuminate\Http\Request;
use NetServa\Fleet\Models\FleetVhost;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set Fleet Context for CMS Frontend
 *
 * Automatically sets the fleet context based on the current domain.
 * This enables per-vhost palette resolution for CMS frontend visitors.
 *
 * Flow:
 * 1. Extract domain from request
 * 2. Look up FleetVhost by domain
 * 3. Set fleet context via PaletteResolver
 * 4. PaletteResolver will traverse: vhost → vnode → vsite → venue
 *
 * Example:
 * - Visitor hits example.com
 * - Middleware finds FleetVhost with domain="example.com"
 * - Sets context: ['type' => 'vhost', 'id' => 123]
 * - PaletteResolver checks vhost palette, then vnode, then vsite, then venue
 * - Falls back to cms.frontend_palette_id setting if no fleet palette found
 * - Finally falls back to system default
 */
class SetFleetContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for admin panel routes
        if ($request->is('admin/*') || $request->is('filament/*')) {
            return $next($request);
        }

        try {
            // Get current domain from request
            $domain = $request->getHost();

            // Find vhost by domain
            $vhost = FleetVhost::where('domain', $domain)->first();

            if ($vhost) {
                // Set fleet context for palette resolution
                $resolver = app(PaletteResolver::class);
                $resolver->setContext('vhost', $vhost->id);
            }
        } catch (\Exception $e) {
            // Silently continue if fleet system unavailable
            // PaletteResolver will fall back to cms.frontend_palette_id or system default
        }

        return $next($request);
    }
}
