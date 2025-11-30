<?php

namespace NetServa\Dns\Services;

use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\Providers\CloudFlareClient;
use NetServa\Dns\Services\Providers\DnsmasqClient;
use NetServa\Dns\Services\Providers\PowerDnsClient;

class DnsProviderFactory
{
    public function create(DnsProvider $provider)
    {
        return self::make($provider);
    }

    public static function make(DnsProvider $provider)
    {
        $config = $provider->connection_config;

        return match ($provider->type) {
            'powerdns' => new PowerDnsClient($config),
            'cloudflare' => new CloudFlareClient($config),
            'dnsmasq' => new DnsmasqClient($config),
            default => throw new \InvalidArgumentException("Unsupported DNS provider type: {$provider->type}")
        };
    }
}
