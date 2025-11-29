<?php

namespace NetServa\Core\Console\Traits;

use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

use function Laravel\Prompts\error;

/**
 * Smart Owner Resolution for VPass Commands
 *
 * Provides NetServa-style smart resolution:
 * - markc              → VNode (no dots)
 * - example.com        → VHost (has dots = domain)
 * - markc example.com  → VHost on specific VNode
 */
trait ResolvesVPassOwner
{
    /**
     * Smart owner resolution
     */
    protected function resolveOwner(?string $name, ?string $domain = null): ?object
    {
        if (! $name) {
            return null;
        }

        // Pattern 1: vnode + domain (explicit)
        if ($domain) {
            $vhost = FleetVhost::where('domain', $domain)
                ->orWhere('fqdn', $domain)
                ->whereHas('vnode', fn ($q) => $q->where('name', $name))
                ->first();

            if (! $vhost) {
                error("VHost {$domain} not found on vnode {$name}");

                return null;
            }

            return $vhost;
        }

        // Pattern 2: Infer from name
        if (str_contains($name, '.')) {
            // Has dots = domain = VHost
            $vhost = FleetVhost::where('domain', $name)
                ->orWhere('fqdn', $name)
                ->first();

            if (! $vhost) {
                error("VHost not found: {$name}");

                return null;
            }

            return $vhost;
        }

        // Pattern 3: No dots = VNode
        $vnode = FleetVnode::where('name', $name)->first();

        if (! $vnode) {
            error("VNode not found: {$name}");

            return null;
        }

        return $vnode;
    }

    /**
     * Get display context for owner
     */
    protected function getOwnerContext(object $owner, string $name, ?string $domain = null): string
    {
        if ($owner instanceof FleetVhost) {
            $vnodeName = $owner->vnode->name ?? 'unknown';

            return $domain ? "{$name}/{$domain}" : $name;
        }

        if ($owner instanceof FleetVnode) {
            return $name;
        }

        if ($owner instanceof FleetVsite) {
            return "vsite:{$owner->name}";
        }

        if ($owner instanceof FleetVenue) {
            return "venue:{$owner->name}";
        }

        return get_class($owner).': '.($owner->name ?? $owner->id);
    }

    /**
     * Get owner type display name
     */
    protected function getOwnerTypeDisplay(object $owner): string
    {
        return match (true) {
            $owner instanceof FleetVhost => 'VHost',
            $owner instanceof FleetVnode => 'VNode',
            $owner instanceof FleetVsite => 'VSite',
            $owner instanceof FleetVenue => 'Venue',
            default => 'Unknown',
        };
    }
}
