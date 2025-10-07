<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WireGuard Manager Configuration
    |--------------------------------------------------------------------------
    | Network management and hub orchestration settings
    */
    'enabled' => env('WIREGUARD_MANAGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Hub Types and Network Allocation
    |--------------------------------------------------------------------------
    | Define available hub types and their network ranges
    */
    'hub_types' => [
        'workstation' => [
            'name' => 'Workstation Hub',
            'description' => 'Developer and admin access',
            'default_network' => '10.100.0.0/24',
            'default_port' => 51820,
            'max_peers' => 50,
            'features' => ['internet_access', 'ssh_distribution', 'development_tools'],
        ],
        'logging' => [
            'name' => 'Central Logging Hub',
            'description' => 'Log aggregation and monitoring',
            'default_network' => '10.200.0.0/24',
            'default_port' => 51821,
            'max_peers' => 200,
            'features' => ['log_forwarding', 'monitoring_agents', 'restricted_routing'],
        ],
        'gateway' => [
            'name' => 'Gateway Hub',
            'description' => 'Network services and routing',
            'default_network' => '10.300.0.0/24',
            'default_port' => 51822,
            'max_peers' => 100,
            'features' => ['nat_routing', 'dns_forwarding', 'firewall_integration'],
        ],
        'customer' => [
            'name' => 'Customer Hub',
            'description' => 'Customer-specific isolated network',
            'default_network' => '10.400.0.0/24',
            'default_port' => 51823,
            'max_peers' => 30,
            'features' => ['complete_isolation', 'custom_routing', 'billing_integration'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Configuration
    |--------------------------------------------------------------------------
    | Global network settings and IP allocation
    */
    'network' => [
        'auto_allocate_ips' => true,
        'ip_allocation_strategy' => 'sequential', // sequential, random, pool
        'reserved_ips' => ['.1', '.254'], // Always reserve these IPs
        'dns_servers' => ['1.1.1.1', '8.8.8.8'],
        'search_domains' => ['local'],
        'mtu' => 1420,
        'persistent_keepalive' => 25, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    | Key management and security configuration
    */
    'security' => [
        'key_rotation_enabled' => true,
        'key_rotation_days' => 90,
        'auto_rotate_on_compromise' => true,
        'require_preshared_keys' => false,
        'max_connection_attempts' => 5,
        'connection_timeout' => 30, // seconds
        'allowed_ciphers' => ['ChaCha20Poly1305', 'AES256-GCM'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Health Checks
    |--------------------------------------------------------------------------
    | Connection monitoring and health verification
    */
    'monitoring' => [
        'enabled' => true,
        'health_check_interval' => 60, // seconds
        'connection_timeout_threshold' => 300, // seconds
        'bandwidth_monitoring' => true,
        'latency_monitoring' => true,
        'packet_loss_threshold' => 5, // percentage
        'alert_on_disconnection' => true,
        'collect_statistics' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    | Central logging hub specific settings
    */
    'logging' => [
        'central_hub_enabled' => true,
        'log_forwarding_port' => 5514,
        'log_formats' => ['syslog', 'json', 'gelf'],
        'compression_enabled' => true,
        'encryption_enabled' => true,
        'buffer_size' => 1048576, // 1MB
        'batch_size' => 100,
        'flush_interval' => 5, // seconds
        'retention_days' => 365,
        'supported_protocols' => ['tcp', 'udp', 'tls'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    | NS plugin integration configuration
    */
    'integrations' => [
        'ssh_manager' => [
            'enabled' => true,
            'auto_create_spokes' => true,
            'failover_enabled' => true,
            'prefer_wireguard' => false,
        ],
        'monitoring_manager' => [
            'enabled' => true,
            'collect_bandwidth_metrics' => true,
            'collect_latency_metrics' => true,
            'collect_connection_metrics' => true,
        ],
        'audit_manager' => [
            'enabled' => true,
            'log_connections' => true,
            'log_disconnections' => true,
            'log_key_rotations' => true,
            'log_configuration_changes' => true,
        ],
        'infrastructure_manager' => [
            'enabled' => true,
            'auto_provision_spokes' => true,
            'link_to_nodes' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    | Automated deployment and configuration management
    */
    'deployment' => [
        'auto_deploy_enabled' => true,
        'deployment_method' => 'ssh', // ssh, ansible, terraform
        'config_backup_enabled' => true,
        'rollback_enabled' => true,
        'validation_enabled' => true,
        'post_deploy_verification' => true,
        'restart_services' => true,
        'firewall_management' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Firewall Rules
    |--------------------------------------------------------------------------
    | Default firewall configuration for hubs
    */
    'firewall' => [
        'enabled' => true,
        'default_policy' => 'drop',
        'hub_rules' => [
            'workstation' => [
                ['action' => 'allow', 'protocol' => 'tcp', 'port' => '22', 'comment' => 'SSH'],
                ['action' => 'allow', 'protocol' => 'tcp', 'port' => '80,443', 'comment' => 'HTTP/HTTPS'],
                ['action' => 'allow', 'protocol' => 'icmp', 'comment' => 'Ping'],
            ],
            'logging' => [
                ['action' => 'allow', 'protocol' => 'tcp', 'port' => '5514', 'comment' => 'Syslog'],
                ['action' => 'allow', 'protocol' => 'udp', 'port' => '5514', 'comment' => 'Syslog UDP'],
                ['action' => 'allow', 'protocol' => 'tcp', 'port' => '9200', 'comment' => 'Elasticsearch'],
                ['action' => 'deny', 'protocol' => 'all', 'port' => 'all', 'comment' => 'Deny other traffic'],
            ],
            'gateway' => [
                ['action' => 'allow', 'protocol' => 'tcp', 'port' => '53', 'comment' => 'DNS'],
                ['action' => 'allow', 'protocol' => 'udp', 'port' => '53', 'comment' => 'DNS'],
                ['action' => 'allow', 'protocol' => 'tcp', 'port' => '80,443', 'comment' => 'HTTP/HTTPS'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    | Performance optimization configuration
    */
    'performance' => [
        'enable_compression' => true,
        'enable_tcp_optimization' => true,
        'buffer_sizes' => [
            'send_buffer' => 262144, // 256KB
            'receive_buffer' => 262144, // 256KB
        ],
        'connection_pooling' => true,
        'max_concurrent_connections' => 100,
        'bandwidth_limits' => [
            'per_peer_mbps' => 100,
            'total_hub_mbps' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Hub Settings
    |--------------------------------------------------------------------------
    | Customer-specific network configuration
    */
    'customer_hubs' => [
        'isolation_enabled' => true,
        'custom_networks' => true,
        'billing_integration' => false,
        'bandwidth_accounting' => true,
        'traffic_shaping' => true,
        'network_range_start' => '10.400.0.0/24',
        'max_customer_hubs' => 50,
        'default_peer_limit' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Settings
    |--------------------------------------------------------------------------
    | Automated management features
    */
    'automation' => [
        'auto_peer_provisioning' => true,
        'auto_key_rotation' => true,
        'auto_health_recovery' => true,
        'auto_firewall_updates' => true,
        'auto_dns_registration' => true,
        'spoke_lifecycle_management' => true,
        'configuration_drift_detection' => true,
        'compliance_checking' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | External Tools Integration
    |--------------------------------------------------------------------------
    | Third-party tool integration settings
    */
    'external_tools' => [
        'wg_tools_path' => '/usr/bin',
        'iptables_path' => '/sbin/iptables',
        'systemctl_path' => '/bin/systemctl',
        'ip_path' => '/sbin/ip',
        'wireguard_config_path' => '/etc/wireguard',
        'backup_path' => '/var/backups/wireguard',
        'log_path' => '/var/log/wireguard',
    ],

    /*
    |--------------------------------------------------------------------------
    | API and Webhook Settings
    |--------------------------------------------------------------------------
    | External integration endpoints
    */
    'api' => [
        'enabled' => true,
        'authentication_required' => true,
        'rate_limiting' => true,
        'requests_per_minute' => 100,
        'webhook_endpoints' => [
            'connection_events' => env('WG_WEBHOOK_CONNECTIONS'),
            'health_alerts' => env('WG_WEBHOOK_HEALTH'),
            'key_rotation_events' => env('WG_WEBHOOK_KEYS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    | Default values for WireGuard components
    */
    'defaults' => [
        'hub_listen_port' => 51820,
        'persistent_keepalive' => 25,
        'mtu' => 1420,
        'dns_servers' => ['1.1.1.1', '8.8.8.8'],
        'allowed_ips' => '0.0.0.0/0',
        'endpoint_resolution_retries' => 3,
        'connection_retry_interval' => 5,
        'key_rotation_warning_days' => 7,
        'health_check_timeout' => 30,
        'bandwidth_limit_mbps' => 100,
    ],
];
