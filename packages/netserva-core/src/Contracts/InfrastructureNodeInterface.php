<?php

namespace NetServa\Core\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NetServa Core Platform Node Interface
 *
 * Standardized interface for platform nodes across the NetServa ecosystem.
 * Part of the NetServa Core foundation package.
 */
interface PlatformNodeInterface
{
    /**
     * Get the parent node
     */
    public function parent(): BelongsTo;

    /**
     * Get child nodes
     */
    public function children(): HasMany;

    /**
     * Get the full hierarchical path
     */
    public function getFullPathAttribute(): string;

    /**
     * Check if node is active
     */
    public function isActive(): bool;

    /**
     * Check if node is a host
     */
    public function isHost(): bool;

    /**
     * Check if node is a service
     */
    public function isService(): bool;

    /**
     * Get node status
     */
    public function getStatus(): string;

    /**
     * Update node status
     */
    public function updateStatus(string $status): bool;
}
