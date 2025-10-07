<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fleet Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for NetServa fleet discovery and management
    |
    */

    'discovery' => [
        // Default scan frequency in hours
        'default_scan_frequency' => 24,

        // SSH connection timeout in seconds
        'ssh_timeout' => 30,

        // Commands to run for different node roles
        'discovery_commands' => [
            'compute' => [
                'hostname -f',
                'uname -a',
                'cat /proc/cpuinfo | grep "processor" | wc -l',
                'free -m | grep "Mem:" | awk \'{print $2}\'',
                'df -h / | tail -1 | awk \'{print $2}\'',
                'ps aux --no-headers | wc -l',
                'ip addr show | grep "inet " | grep -v "127.0.0.1"',
            ],
            'network' => [
                'hostname',
                'uptime',
                'ip addr show',
            ],
            'storage' => [
                'hostname -f',
                'df -h',
                'lsblk',
                'zfs list 2>/dev/null || echo "ZFS not available"',
            ],
        ],

        // Patterns to exclude during vhost discovery
        'exclude_patterns' => [
            '.*',
            '*.tmp',
            '*.bak',
            '*.log',
        ],
    ],

    'vsites' => [
        // Mapping for current var/ directory structure (2-tier: vnode/vhost)
        // Maps vnode names to their VSite configuration
        'vnode_to_vsite_mappings' => [
            'mgo' => ['provider' => 'local', 'technology' => 'incus', 'location' => 'workstation'],
            'haproxy' => ['provider' => 'local', 'technology' => 'proxmox', 'location' => 'homelab'],
            'nsorg' => ['provider' => 'binarylane', 'technology' => 'vps', 'location' => 'sydney'],
            'ns2' => ['provider' => 'binarylane', 'technology' => 'vps', 'location' => 'sydney'],
            'motd' => ['provider' => 'customer', 'technology' => 'vps', 'location' => 'sydney'],
            'mrn' => ['provider' => 'customer', 'technology' => 'hardware', 'location' => 'melbourne'],
            'pbe' => ['provider' => 'customer', 'technology' => 'hardware', 'location' => 'perth'],
            'bion' => ['provider' => 'local', 'technology' => 'hardware', 'location' => 'workstation'],
        ],

        // Default capabilities by technology
        'default_capabilities' => [
            'incus' => ['containers', 'vms', 'snapshots', 'clustering'],
            'proxmox' => ['vms', 'containers', 'clustering', 'backup'],
            'vps' => ['compute', 'networking'],
            'hardware' => ['compute', 'storage', 'networking'],
            'router' => ['networking', 'firewall'],
        ],
    ],
];
