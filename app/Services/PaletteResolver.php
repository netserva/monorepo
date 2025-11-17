<?php

namespace App\Services;

use App\Models\Palette;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * PaletteResolver - Hierarchical Palette Resolution Service
 *
 * Resolution order:
 * 1. Session override (demos, previews)
 * 2. Fleet context (vhost → vnode → vsite → venue)
 * 3. User preference
 * 4. System default (slate)
 */
class PaletteResolver
{
    /**
     * Get the current effective palette based on hierarchy.
     */
    public function getCurrentPalette(): Palette
    {
        // 1. Check session override (highest priority)
        if ($paletteId = session('palette_override')) {
            $palette = Palette::find($paletteId);
            if ($palette) {
                return $palette;
            }
        }

        // 2. Check current fleet context
        if ($context = session('fleet_context')) {
            $palette = $this->resolveFromContext($context);
            if ($palette) {
                return $palette;
            }
        }

        // 3. User preference (for authenticated admin users)
        if (auth()->check() && auth()->user()->palette_id) {
            $palette = auth()->user()->palette;
            if ($palette) {
                return $palette;
            }
        }

        // 4. Site-wide frontend palette (for public visitors)
        if (class_exists(\NetServa\Core\Models\Setting::class)) {
            try {
                $frontendPaletteId = \NetServa\Core\Models\Setting::getValue('cms.frontend_palette_id');
                if ($frontendPaletteId) {
                    $palette = Palette::find($frontendPaletteId);
                    if ($palette) {
                        return $palette;
                    }
                }
            } catch (\Exception $e) {
                // Silently continue to default if settings unavailable
            }
        }

        // 5. System default
        return Palette::default();
    }

    /**
     * Resolve palette from fleet context.
     * Hierarchy: vhost → vnode → vsite → venue
     */
    protected function resolveFromContext(array $context): ?Palette
    {
        $type = $context['type'] ?? null;
        $id = $context['id'] ?? null;

        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'vhost' => $this->resolveFromVhost($id),
            'vnode' => $this->resolveFromVnode($id),
            'vsite' => $this->resolveFromVsite($id),
            'venue' => $this->resolveFromVenue($id),
            default => null,
        };
    }

    /**
     * Resolve palette starting from vhost.
     * Checks: vhost → vnode → vsite → venue
     */
    protected function resolveFromVhost(int $vhostId): ?Palette
    {
        $vhost = FleetVhost::find($vhostId);
        if (! $vhost) {
            return null;
        }

        // Check vhost palette
        if ($vhost->palette_id && $vhost->palette) {
            return $vhost->palette;
        }

        // Check vnode palette
        if ($vhost->vnode_id) {
            $palette = $this->resolveFromVnode($vhost->vnode_id);
            if ($palette) {
                return $palette;
            }
        }

        return null;
    }

    /**
     * Resolve palette starting from vnode.
     * Checks: vnode → vsite → venue
     */
    protected function resolveFromVnode(int $vnodeId): ?Palette
    {
        $vnode = FleetVnode::find($vnodeId);
        if (! $vnode) {
            return null;
        }

        // Check vnode palette
        if ($vnode->palette_id && $vnode->palette) {
            return $vnode->palette;
        }

        // Check vsite palette
        if ($vnode->vsite_id) {
            $palette = $this->resolveFromVsite($vnode->vsite_id);
            if ($palette) {
                return $palette;
            }
        }

        return null;
    }

    /**
     * Resolve palette starting from vsite.
     * Checks: vsite → venue
     */
    protected function resolveFromVsite(int $vsiteId): ?Palette
    {
        $vsite = FleetVsite::find($vsiteId);
        if (! $vsite) {
            return null;
        }

        // Check vsite palette
        if ($vsite->palette_id && $vsite->palette) {
            return $vsite->palette;
        }

        // Check venue palette
        if ($vsite->venue_id) {
            return $this->resolveFromVenue($vsite->venue_id);
        }

        return null;
    }

    /**
     * Resolve palette from venue.
     */
    protected function resolveFromVenue(int $venueId): ?Palette
    {
        $venue = FleetVenue::find($venueId);
        if (! $venue) {
            return null;
        }

        return $venue->palette_id ? $venue->palette : null;
    }

    /**
     * Set the current fleet context for palette resolution.
     *
     * @param  string  $type  Entity type: 'vhost', 'vnode', 'vsite', 'venue'
     * @param  int  $id  Entity ID
     */
    public function setContext(string $type, int $id): void
    {
        session()->put('fleet_context', [
            'type' => $type,
            'id' => $id,
        ]);
    }

    /**
     * Get current context information for UI display.
     *
     * @return array|null ['type' => 'Venue', 'name' => 'Acme', 'icon' => '🏢']
     */
    public function getCurrentContext(): ?array
    {
        $context = session('fleet_context');
        if (! $context) {
            return null;
        }

        $type = $context['type'] ?? null;
        $id = $context['id'] ?? null;

        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'venue' => [
                'type' => 'Venue',
                'name' => FleetVenue::find($id)?->name ?? 'Unknown',
                'icon' => '🏢',
            ],
            'vsite' => [
                'type' => 'VSite',
                'name' => FleetVsite::find($id)?->name ?? 'Unknown',
                'icon' => '🌐',
            ],
            'vnode' => [
                'type' => 'VNode',
                'name' => FleetVnode::find($id)?->name ?? 'Unknown',
                'icon' => '🖥️',
            ],
            'vhost' => [
                'type' => 'VHost',
                'name' => FleetVhost::find($id)?->domain ?? 'Unknown',
                'icon' => '🌍',
            ],
            default => null,
        };
    }

    /**
     * Get the context type for reset button display.
     */
    public function getContextType(): string
    {
        $context = session('fleet_context');
        if (! $context) {
            return 'User';
        }

        return match ($context['type'] ?? null) {
            'venue' => 'Venue',
            'vsite' => 'VSite',
            'vnode' => 'VNode',
            'vhost' => 'VHost',
            default => 'User',
        };
    }

    /**
     * Clear session palette override.
     */
    public function clearOverride(): void
    {
        session()->forget('palette_override');
    }

    /**
     * Set session palette override.
     */
    public function setOverride(int $paletteId): void
    {
        session()->put('palette_override', $paletteId);
    }

    /**
     * Check if there's an active session override.
     */
    public function hasOverride(): bool
    {
        return session()->has('palette_override');
    }
}
