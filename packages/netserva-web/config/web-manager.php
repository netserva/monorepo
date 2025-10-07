<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Manager Configuration
    |--------------------------------------------------------------------------
    | Core web server management settings
    */
    'enabled' => env('WEB_MANAGER_ENABLED', true),

    'default_web_server' => env('DEFAULT_WEB_SERVER', 'nginx'),

    'nginx_config_path' => env('NGINX_CONFIG_PATH', '/etc/nginx'),

    'apache_config_path' => env('APACHE_CONFIG_PATH', '/etc/apache2'),

    /*
    |--------------------------------------------------------------------------
    | Web Server Types
    |--------------------------------------------------------------------------
    | Supported web server configurations and handlers
    */
    'web_server_types' => [
        'nginx' => [
            'name' => 'Nginx',
            'handler' => \NetServa\Web\Services\NginxService::class,
            'config_path' => '/etc/nginx',
            'sites_path' => '/etc/nginx/sites-available',
            'enabled_path' => '/etc/nginx/sites-enabled',
            'service_name' => 'nginx',
            'supports_php_fpm' => true,
            'supports_ssl' => true,
            'supports_http2' => true,
            'supports_load_balancing' => true,
        ],

        'apache' => [
            'name' => 'Apache HTTP Server',
            'handler' => \NetServa\Web\Services\ApacheService::class,
            'config_path' => '/etc/apache2',
            'sites_path' => '/etc/apache2/sites-available',
            'enabled_path' => '/etc/apache2/sites-enabled',
            'service_name' => 'apache2',
            'supports_php_fpm' => true,
            'supports_ssl' => true,
            'supports_http2' => true,
            'supports_load_balancing' => true,
        ],

        'lighttpd' => [
            'name' => 'Lighttpd',
            'handler' => \NetServa\Web\Services\LighttpdService::class,
            'config_path' => '/etc/lighttpd',
            'sites_path' => '/etc/lighttpd/conf-available',
            'enabled_path' => '/etc/lighttpd/conf-enabled',
            'service_name' => 'lighttpd',
            'supports_php_fpm' => true,
            'supports_ssl' => true,
            'supports_http2' => false,
            'supports_load_balancing' => false,
        ],

        'caddy' => [
            'name' => 'Caddy Server',
            'handler' => \NetServa\Web\Services\CaddyService::class,
            'config_path' => '/etc/caddy',
            'sites_path' => '/etc/caddy/sites',
            'enabled_path' => '/etc/caddy/sites',
            'service_name' => 'caddy',
            'supports_php_fpm' => true,
            'supports_ssl' => true,
            'supports_http2' => true,
            'supports_load_balancing' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP Configuration
    |--------------------------------------------------------------------------
    | PHP-FPM and runtime settings
    */
    'php' => [
        'versions' => ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'],
        'default_version' => '8.3',
        'fpm_path' => '/etc/php/{version}/fpm',
        'pool_path' => '/etc/php/{version}/fpm/pool.d',
        'socket_path' => '/var/run/php/php{version}-fpm.sock',
        'service_template' => 'php{version}-fpm',

        'pool_defaults' => [
            'pm' => 'dynamic',
            'pm.max_children' => 50,
            'pm.start_servers' => 5,
            'pm.min_spare_servers' => 5,
            'pm.max_spare_servers' => 35,
            'pm.process_idle_timeout' => '10s',
            'pm.max_requests' => 500,
        ],

        'security_settings' => [
            'expose_php' => 'Off',
            'display_errors' => 'Off',
            'display_startup_errors' => 'Off',
            'log_errors' => 'On',
            'allow_url_fopen' => 'Off',
            'allow_url_include' => 'Off',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Types
    |--------------------------------------------------------------------------
    | Supported web application frameworks and CMS
    */
    'application_types' => [
        'wordpress' => [
            'name' => 'WordPress',
            'handler' => \NetServa\Web\Applications\WordPressHandler::class,
            'requirements' => ['php', 'mysql'],
            'auto_install' => true,
            'config_files' => ['wp-config.php'],
            'update_method' => 'wp-cli',
        ],

        'laravel' => [
            'name' => 'Laravel',
            'handler' => \NetServa\Web\Applications\LaravelHandler::class,
            'requirements' => ['php', 'composer'],
            'auto_install' => false,
            'config_files' => ['.env'],
            'update_method' => 'composer',
        ],

        'symfony' => [
            'name' => 'Symfony',
            'handler' => \NetServa\Web\Applications\SymfonyHandler::class,
            'requirements' => ['php', 'composer'],
            'auto_install' => false,
            'config_files' => ['.env'],
            'update_method' => 'composer',
        ],

        'drupal' => [
            'name' => 'Drupal',
            'handler' => \NetServa\Web\Applications\DrupalHandler::class,
            'requirements' => ['php', 'mysql', 'composer'],
            'auto_install' => true,
            'config_files' => ['sites/default/settings.php'],
            'update_method' => 'composer',
        ],

        'static' => [
            'name' => 'Static HTML',
            'handler' => \NetServa\Web\Applications\StaticHandler::class,
            'requirements' => [],
            'auto_install' => false,
            'config_files' => [],
            'update_method' => 'git',
        ],

        'nodejs' => [
            'name' => 'Node.js Application',
            'handler' => \NetServa\Web\Applications\NodeJsHandler::class,
            'requirements' => ['nodejs', 'npm'],
            'auto_install' => false,
            'config_files' => ['package.json', '.env'],
            'update_method' => 'npm',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL/TLS Configuration
    |--------------------------------------------------------------------------
    | SSL certificate management settings
    */
    'ssl' => [
        'enabled' => env('WEB_SSL_ENABLED', true),
        'cert_path' => env('WEB_SSL_CERT_PATH', '/etc/ssl/certs'),
        'key_path' => env('WEB_SSL_KEY_PATH', '/etc/ssl/private'),
        'auto_redirect' => true,
        'hsts_enabled' => true,
        'hsts_max_age' => 31536000, // 1 year
        'protocols' => ['TLSv1.2', 'TLSv1.3'],
        'ciphers' => 'EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH',

        'lets_encrypt' => [
            'enabled' => true,
            'email' => env('LETSENCRYPT_EMAIL'),
            'staging' => env('LETSENCRYPT_STAGING', false),
            'auto_renew' => true,
            'renewal_days' => 30, // Renew when 30 days left
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Balancing
    |--------------------------------------------------------------------------
    | Load balancer and proxy settings
    */
    'load_balancing' => [
        'enabled' => env('LOAD_BALANCING_ENABLED', false),
        'methods' => [
            'round_robin' => 'Round Robin',
            'least_conn' => 'Least Connections',
            'ip_hash' => 'IP Hash',
            'least_time' => 'Least Time',
            'random' => 'Random',
        ],
        'default_method' => 'round_robin',
        'health_checks' => true,
        'health_check_interval' => 30,
        'fail_timeout' => 30,
        'max_fails' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    | Web caching and CDN settings
    */
    'caching' => [
        'enabled' => env('WEB_CACHING_ENABLED', true),

        'types' => [
            'fastcgi' => [
                'name' => 'FastCGI Cache',
                'handler' => \NetServa\Web\Cache\FastCgiCacheHandler::class,
                'path' => '/var/cache/nginx/fastcgi',
                'zone_size' => '10m',
                'max_size' => '1g',
                'inactive' => '60m',
            ],

            'proxy' => [
                'name' => 'Proxy Cache',
                'handler' => \NetServa\Web\Cache\ProxyCacheHandler::class,
                'path' => '/var/cache/nginx/proxy',
                'zone_size' => '10m',
                'max_size' => '1g',
                'inactive' => '60m',
            ],

            'redis' => [
                'name' => 'Redis Cache',
                'handler' => \NetServa\Web\Cache\RedisCacheHandler::class,
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'database' => 0,
            ],

            'memcached' => [
                'name' => 'Memcached',
                'handler' => \NetServa\Web\Cache\MemcachedHandler::class,
                'servers' => [
                    ['host' => '127.0.0.1', 'port' => 11211],
                ],
            ],
        ],

        'default_cache' => 'fastcgi',
        'cache_key_zones' => [
            'fastcgi' => 'fastcgi_cache_zone',
            'proxy' => 'proxy_cache_zone',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    | Web server security configuration
    */
    'security' => [
        'enabled' => true,

        'headers' => [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ],

        'rate_limiting' => [
            'enabled' => true,
            'zone_size' => '10m',
            'rate' => '10r/s',
            'burst' => 20,
            'delay' => 5,
        ],

        'fail2ban' => [
            'enabled' => env('FAIL2BAN_ENABLED', true),
            'jail_time' => 600, // 10 minutes
            'max_retry' => 5,
            'find_time' => 300, // 5 minutes
        ],

        'modsecurity' => [
            'enabled' => env('MODSECURITY_ENABLED', false),
            'rules_path' => '/etc/modsecurity',
            'audit_log' => '/var/log/modsec_audit.log',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    | Web server monitoring and metrics
    */
    'monitoring' => [
        'enabled' => env('WEB_MONITORING_ENABLED', true),
        'check_interval_minutes' => 5,
        'metrics_retention_days' => 30,

        'checks' => [
            'http_response' => true,
            'ssl_certificate' => true,
            'response_time' => true,
            'disk_space' => true,
            'memory_usage' => true,
            'cpu_usage' => true,
            'connection_count' => true,
        ],

        'alert_thresholds' => [
            'response_time_ms' => 5000,
            'error_rate_percent' => 5,
            'disk_usage_percent' => 90,
            'memory_usage_percent' => 85,
            'cpu_usage_percent' => 80,
        ],

        'tools' => [
            'nginx_status' => [
                'enabled' => true,
                'url' => 'http://localhost/nginx_status',
            ],
            'php_fpm_status' => [
                'enabled' => true,
                'url' => 'http://localhost/fpm-status',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    | Web content backup settings
    */
    'backup' => [
        'enabled' => env('WEB_BACKUP_ENABLED', true),
        'backup_path' => env('WEB_BACKUP_PATH', '/var/backups/web'),
        'schedule' => '0 2 * * *', // Daily at 2 AM
        'retention_days' => 30,
        'compress' => true,
        'exclude_patterns' => [
            '*.log',
            '*.cache',
            'node_modules/*',
            '.git/*',
            'vendor/*',
            'wp-content/cache/*',
            'storage/logs/*',
            'var/cache/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    | Application deployment settings
    */
    'deployment' => [
        'enabled' => true,
        'methods' => [
            'git' => [
                'name' => 'Git Repository',
                'handler' => \NetServa\Web\Deployment\GitDeployment::class,
                'supported_hooks' => ['pre-receive', 'post-receive', 'pre-deploy', 'post-deploy'],
            ],
            'rsync' => [
                'name' => 'Rsync',
                'handler' => \NetServa\Web\Deployment\RsyncDeployment::class,
                'supported_hooks' => ['pre-deploy', 'post-deploy'],
            ],
            'ftp' => [
                'name' => 'FTP/SFTP',
                'handler' => \NetServa\Web\Deployment\FtpDeployment::class,
                'supported_hooks' => ['pre-deploy', 'post-deploy'],
            ],
        ],

        'hooks' => [
            'pre-deploy' => [],
            'post-deploy' => [
                'clear_cache',
                'restart_services',
                'run_migrations',
                'update_search_index',
            ],
        ],

        'rollback' => [
            'enabled' => true,
            'keep_releases' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    | Default values for new web servers and virtual hosts
    */
    'defaults' => [
        'server_tokens' => 'off',
        'client_max_body_size' => '64M',
        'keepalive_timeout' => 65,
        'gzip' => true,
        'gzip_types' => [
            'text/plain',
            'text/css',
            'application/json',
            'application/javascript',
            'text/xml',
            'application/xml',
            'application/xml+rss',
            'text/javascript',
            'image/svg+xml',
        ],
        'index_files' => ['index.php', 'index.html', 'index.htm'],
        'error_pages' => [
            '404' => '/error-404.html',
            '50x' => '/error-50x.html',
        ],
        'log_format' => 'combined',
        'access_log' => true,
        'error_log' => true,
    ],
];
