<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use NetServa\Core\ValueObjects\OsConfiguration;
use NetServa\Core\ValueObjects\VhostConfiguration;

/**
 * Lazy Configuration Cache Service
 *
 * Implements lazy loading patterns for expensive NetServa configuration operations
 */
class LazyConfigurationCache
{
    protected const CACHE_TTL = 300; // 5 minutes

    protected const OS_CONFIG_PREFIX = 'os_config:';

    protected const SERVER_FQDN_PREFIX = 'server_fqdn:';

    protected const SERVER_UID_PREFIX = 'server_uid:';

    protected const SERVER_IP_PREFIX = 'server_ip:';

    protected Collection $memoryCache;

    public function __construct()
    {
        $this->memoryCache = collect();
    }

    /**
     * Get OS configuration with lazy loading and caching
     */
    public function getOsConfiguration(string $VNODE, callable $detector): OsConfiguration
    {
        $memoryKey = "os:{$VNODE}";

        if ($this->memoryCache->has($memoryKey)) {
            return $this->memoryCache->get($memoryKey);
        }

        $cacheKey = self::OS_CONFIG_PREFIX.$VNODE;

        $osConfig = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($detector) {
            $result = $detector();

            // Convert to serializable array for cache storage
            return [
                'type' => $result->type->value,
                'release' => $result->release,
                'mirror' => $result->mirror,
            ];
        });

        // Reconstruct OsConfiguration from cached data
        $reconstructed = OsConfiguration::fromStrings(
            $osConfig['type'],
            $osConfig['release'],
            $osConfig['mirror']
        );

        $this->memoryCache->put($memoryKey, $reconstructed);

        return $reconstructed;
    }

    /**
     * Get server FQDN with lazy loading and caching
     */
    public function getServerFqdn(string $VNODE, callable $resolver): string
    {
        $memoryKey = "fqdn:{$VNODE}";

        if ($this->memoryCache->has($memoryKey)) {
            return $this->memoryCache->get($memoryKey);
        }

        $cacheKey = self::SERVER_FQDN_PREFIX.$VNODE;

        $fqdn = Cache::remember($cacheKey, self::CACHE_TTL, $resolver);

        $this->memoryCache->put($memoryKey, $fqdn);

        return $fqdn;
    }

    /**
     * Get next available UID with lazy loading and caching
     */
    public function getNextAvailableUid(string $VNODE, callable $resolver): int
    {
        $memoryKey = "uid:{$VNODE}";

        if ($this->memoryCache->has($memoryKey)) {
            return $this->memoryCache->get($memoryKey);
        }

        $cacheKey = self::SERVER_UID_PREFIX.$VNODE;

        // Shorter cache time for UID since it changes more frequently
        $uid = Cache::remember($cacheKey, 60, $resolver);

        $this->memoryCache->put($memoryKey, $uid);

        return $uid;
    }

    /**
     * Get server IP address with lazy loading and caching
     */
    public function getServerIp(string $VNODE, callable $resolver): string
    {
        $memoryKey = "ip:{$VNODE}";

        if ($this->memoryCache->has($memoryKey)) {
            return $this->memoryCache->get($memoryKey);
        }

        $cacheKey = self::SERVER_IP_PREFIX.$VNODE;

        $ip = Cache::remember($cacheKey, self::CACHE_TTL, $resolver);

        $this->memoryCache->put($memoryKey, $ip);

        return $ip;
    }

    /**
     * Invalidate all cached data for a server
     */
    public function invalidateServer(string $VNODE): void
    {
        $this->memoryCache->forget("os:{$VNODE}");
        $this->memoryCache->forget("fqdn:{$VNODE}");
        $this->memoryCache->forget("uid:{$VNODE}");
        $this->memoryCache->forget("ip:{$VNODE}");

        Cache::forget(self::OS_CONFIG_PREFIX.$VNODE);
        Cache::forget(self::SERVER_FQDN_PREFIX.$VNODE);
        Cache::forget(self::SERVER_UID_PREFIX.$VNODE);
        Cache::forget(self::SERVER_IP_PREFIX.$VNODE);
    }

    /**
     * Get all cached server data (for debugging)
     */
    public function getCacheStats(): array
    {
        return [
            'memory_cache_size' => $this->memoryCache->count(),
            'memory_cache_keys' => $this->memoryCache->keys()->toArray(),
        ];
    }

    /**
     * Clear all cache entries
     */
    public function clearAll(): void
    {
        $this->memoryCache = collect();

        // Note: This would clear ALL cache entries, not just ours
        // In production, you might want to use cache tags instead
        Cache::flush();
    }

    /**
     * Lazy load VHost configuration with dependencies
     */
    public function getVhostConfiguration(
        string $VNODE,
        string $VHOST,
        callable $osDetector,
        callable $fqdnResolver,
        callable $uidResolver,
        callable $ipResolver
    ): VhostConfiguration {
        // Use lazy loading for all dependencies
        $osConfig = $this->getOsConfiguration($VNODE, $osDetector);
        $serverFqdn = $this->getServerFqdn($VNODE, $fqdnResolver);
        $serverIp = $this->getServerIp($VNODE, $ipResolver);

        // Determine UID based on cached FQDN (NetServa logic)
        // If VHOST == server FQDN, use admin UID (1000)
        // Otherwise, get next available UID starting from 1002
        $U_UID = ($serverFqdn === $VHOST)
            ? 1000  // Admin user (sysadm)
            : $this->getNextAvailableUid($VNODE, $uidResolver);  // u1002, u1003, etc.

        // The VhostConfiguration itself could also be cached
        // but passwords should be generated fresh each time
        return app(NetServaConfigurationService::class)->generateVhostConfigFromCache(
            $VNODE,
            $VHOST,
            $U_UID,
            $osConfig,
            $serverIp
        );
    }
}
