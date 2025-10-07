<?php

namespace NetServa\Dns\Services;

use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\Providers\Bind9Client;
use NetServa\Dns\Services\Providers\CloudFlareClient;
use NetServa\Dns\Services\Providers\DigitalOceanClient;
use NetServa\Dns\Services\Providers\GoDaddyClient;
use NetServa\Dns\Services\Providers\PowerDnsClient;
use NetServa\Dns\Services\Providers\Route53Client;

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
            'route53' => new Route53Client($config),
            'digitalocean' => new DigitalOceanClient($config),
            'godaddy' => new GoDaddyClient($config),
            'bind9' => new Bind9Client($config),
            default => throw new \InvalidArgumentException("Unsupported DNS provider type: {$provider->type}")
        };
    }
}
