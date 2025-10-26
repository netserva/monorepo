<?php

namespace NetServa\Cli\Console\Traits;

use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

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
            $vhost = FleetVHost::where('domain', $domain)
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
            $vhost = FleetVHost::where('domain', $name)
                ->orWhere('fqdn', $name)
                ->first();

            if (! $vhost) {
                error("VHost not found: {$name}");

                return null;
            }

            return $vhost;
        }

        // Pattern 3: No dots = VNode
        $vnode = FleetVNode::where('name', $name)->first();

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
        if ($owner instanceof FleetVHost) {
            $vnodeName = $owner->vnode->name ?? 'unknown';

            return $domain ? "{$name}/{$domain}" : $name;
        }

        if ($owner instanceof FleetVNode) {
            return $name;
        }

        if ($owner instanceof FleetVSite) {
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
            $owner instanceof FleetVHost => 'VHost',
            $owner instanceof FleetVNode => 'VNode',
            $owner instanceof FleetVSite => 'VSite',
            $owner instanceof FleetVenue => 'Venue',
            default => 'Unknown',
        };
    }
}
