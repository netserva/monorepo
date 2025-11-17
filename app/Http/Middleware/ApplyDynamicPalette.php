<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyDynamicPalette
{
    /**
     * Handle an incoming request.
     *
     * Apply the current palette colors to Filament on every request.
     * This enables dynamic color switching without panel reconfiguration.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Note: This middleware is no longer needed since colors are registered
        // via closure in AdminPanelProvider::boot() - keeping for future use
        return $next($request);
    }
}
