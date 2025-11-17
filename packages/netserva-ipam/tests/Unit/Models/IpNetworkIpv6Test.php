<?php

use NetServa\Ipam\Models\IpNetwork;

describe('IpNetwork IPv6 Support', function () {
    it('expands compressed IPv6 addresses correctly', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2198:f600::',
            'prefix_length' => 56,
            'ip_version' => '6',
        ]);

        $reflection = new ReflectionClass($network);
        $method = $reflection->getMethod('expandIpv6');
        $method->setAccessible(true);

        expect($method->invoke($network, '2404:9400:2198:f600::103'))
            ->toBe('2404:9400:2198:f600:0000:0000:0000:0103');

        expect($method->invoke($network, '2404:9400:2198:f600::'))
            ->toBe('2404:9400:2198:f600:0000:0000:0000:0000');

        expect($method->invoke($network, '::1'))
            ->toBe('0000:0000:0000:0000:0000:0000:0000:0001');

        expect($method->invoke($network, '2404:9400:2684:7400::103'))
            ->toBe('2404:9400:2684:7400:0000:0000:0000:0103');
    });

    it('calculates IPv6 reverse zone correctly for /56 networks', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2198:f600::',
            'prefix_length' => 56,
            'ip_version' => '6',
        ]);

        // /56 = 14 nibbles (56/4)
        expect($network->getIpv6ReverseZone())
            ->toBe('6.f.8.9.1.2.0.0.4.9.4.0.4.2.ip6.arpa');
    });

    it('calculates IPv6 reverse zone correctly for /64 networks', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2:0::',
            'prefix_length' => 64,
            'ip_version' => '6',
        ]);

        expect($network->getIpv6ReverseZone())
            ->toBe('0.0.0.0.2.0.0.0.0.0.4.9.4.0.4.2.ip6.arpa');
    });

    it('calculates IPv6 reverse zone correctly for /48 networks', function () {
        $network = new IpNetwork([
            'network_address' => '2001:db8:1234::',
            'prefix_length' => 48,
            'ip_version' => '6',
        ]);

        expect($network->getIpv6ReverseZone())
            ->toBe('4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa');
    });

    it('calculates PTR label for IPv6 addresses', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2198:f600::',
            'prefix_length' => 56,
            'ip_version' => '6',
        ]);

        // Test mail.spiderweb.com.au scenario
        expect($network->getIpv6PtrLabel('2404:9400:2198:f600::103'))
            ->toBe('3.0.1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0');

        // Test different host portion
        expect($network->getIpv6PtrLabel('2404:9400:2198:f600::1'))
            ->toBe('1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0');
    });

    it('calculates PTR label for IPv6 addresses in /64 networks', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2684:7400::',
            'prefix_length' => 64,
            'ip_version' => '6',
        ]);

        // Test mail.renta.net scenario
        expect($network->getIpv6PtrLabel('2404:9400:2684:7400::103'))
            ->toBe('3.0.1.0.0.0.0.0.0.0.0.0.0.0.0.0');

        expect($network->getIpv6PtrLabel('2404:9400:2684:7400::1'))
            ->toBe('1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0');
    });

    it('checks if IPv6 address is contained in network', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2198:f600::',
            'prefix_length' => 56,
            'ip_version' => '6',
        ]);

        // Address within network
        expect($network->containsIp('2404:9400:2198:f600::103'))->toBeTrue();
        expect($network->containsIp('2404:9400:2198:f6ff::1'))->toBeTrue();

        // Address outside network (different /56)
        expect($network->containsIp('2404:9400:2684:7400::103'))->toBeFalse();
        expect($network->containsIp('2404:9400:2199:0::1'))->toBeFalse();
    });

    it('returns null for IPv4 networks when calling IPv6 methods', function () {
        $network = new IpNetwork([
            'network_address' => '203.25.132.0',
            'prefix_length' => 24,
            'ip_version' => '4',
        ]);

        expect($network->getIpv6ReverseZone())->toBeNull();
        expect($network->getIpv6PtrLabel('203.25.132.2'))->toBeNull();
    });

    it('returns null for PTR label when IP is not in network', function () {
        $network = new IpNetwork([
            'network_address' => '2404:9400:2198:f600::',
            'prefix_length' => 56,
            'ip_version' => '6',
        ]);

        // IP outside the network
        expect($network->getIpv6PtrLabel('2404:9400:2684:7400::103'))->toBeNull();
    });

    it('handles real-world mail.renta.net scenario', function () {
        // The actual IPv6 configuration from mail.renta.net
        $network = new IpNetwork([
            'name' => 'Mammoth Media Routed Range',
            'network_address' => '2404:9400:2684:7400::',
            'prefix_length' => 56,
            'ip_version' => '6',
            'network_type' => 'public',
        ]);

        $mailIp = '2404:9400:2684:7400::103';

        expect($network->containsIp($mailIp))->toBeTrue();
        // /56 = 14 nibbles, matches actual PowerDNS zone: 4.7.4.8.6.2.0.0.4.9.4.0.4.2.ip6.arpa
        expect($network->getIpv6ReverseZone())
            ->toBe('4.7.4.8.6.2.0.0.4.9.4.0.4.2.ip6.arpa');
        expect($network->getIpv6PtrLabel($mailIp))
            ->toBe('3.0.1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0');
    });

    it('handles real-world mail.spiderweb.com.au scenario', function () {
        // The actual IPv6 configuration from mail.spiderweb.com.au
        $network = new IpNetwork([
            'name' => 'Binary Lane Routed Range',
            'network_address' => '2404:9400:2198:f600::',
            'prefix_length' => 56,
            'ip_version' => '6',
            'network_type' => 'public',
        ]);

        $mailIp = '2404:9400:2198:f600::103';

        expect($network->containsIp($mailIp))->toBeTrue();
        // /56 = 14 nibbles
        expect($network->getIpv6ReverseZone())
            ->toBe('6.f.8.9.1.2.0.0.4.9.4.0.4.2.ip6.arpa');
        expect($network->getIpv6PtrLabel($mailIp))
            ->toBe('3.0.1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0');
    });
});
