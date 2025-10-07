<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mail Manager Configuration
    |--------------------------------------------------------------------------
    | Core email infrastructure management settings
    */
    'enabled' => env('MAIL_MANAGER_ENABLED', true),

    'default_mail_server' => env('DEFAULT_MAIL_SERVER', 'localhost'),

    'postfix_config_path' => env('POSTFIX_CONFIG_PATH', '/etc/postfix'),

    'dovecot_config_path' => env('DOVECOT_CONFIG_PATH', '/etc/dovecot'),

    /*
    |--------------------------------------------------------------------------
    | Mail Server Types
    |--------------------------------------------------------------------------
    | Supported mail server configurations and handlers
    */
    'mail_server_types' => [
        'postfix_dovecot' => [
            'name' => 'Postfix + Dovecot',
            'handler' => \Ns\Mail\Services\PostfixDovecotService::class,
            'smtp_service' => 'postfix',
            'imap_service' => 'dovecot',
            'supports_virtual_domains' => true,
            'supports_sieve' => true,
        ],

        'exim_dovecot' => [
            'name' => 'Exim + Dovecot',
            'handler' => \Ns\Mail\Services\EximDovecotService::class,
            'smtp_service' => 'exim4',
            'imap_service' => 'dovecot',
            'supports_virtual_domains' => true,
            'supports_sieve' => true,
        ],

        'sendmail_courier' => [
            'name' => 'Sendmail + Courier',
            'handler' => \Ns\Mail\Services\SendmailCourierService::class,
            'smtp_service' => 'sendmail',
            'imap_service' => 'courier-imap',
            'supports_virtual_domains' => false,
            'supports_sieve' => false,
        ],

        'custom' => [
            'name' => 'Custom Configuration',
            'handler' => \Ns\Mail\Services\CustomMailService::class,
            'smtp_service' => 'custom',
            'imap_service' => 'custom',
            'supports_virtual_domains' => true,
            'supports_sieve' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Backends
    |--------------------------------------------------------------------------
    | Supported mail storage backends
    */
    'storage_backends' => [
        'maildir' => [
            'name' => 'Maildir',
            'handler' => \Ns\Mail\Storage\MaildirStorage::class,
            'path_template' => '/var/mail/vhosts/{domain}/{user}',
            'supports_quotas' => true,
            'supports_shared_folders' => true,
        ],

        'mbox' => [
            'name' => 'mbox',
            'handler' => \Ns\Mail\Storage\MboxStorage::class,
            'path_template' => '/var/mail/{user}',
            'supports_quotas' => false,
            'supports_shared_folders' => false,
        ],

        'dbox' => [
            'name' => 'dbox (Dovecot)',
            'handler' => \Ns\Mail\Storage\DboxStorage::class,
            'path_template' => '/var/mail/vhosts/{domain}/{user}',
            'supports_quotas' => true,
            'supports_shared_folders' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-Spam Configuration
    |--------------------------------------------------------------------------
    | Spam filtering and protection settings
    */
    'antispam' => [
        'enabled' => env('ANTISPAM_ENABLED', true),

        'engines' => [
            'spamassassin' => [
                'name' => 'SpamAssassin',
                'handler' => \Ns\Mail\AntiSpam\SpamAssassinEngine::class,
                'config_path' => '/etc/spamassassin',
                'socket' => '/var/run/spamassassin/spamd.sock',
                'score_threshold' => 5.0,
            ],

            'rspamd' => [
                'name' => 'RSpamd',
                'handler' => \Ns\Mail\AntiSpam\RSpamdEngine::class,
                'config_path' => '/etc/rspamd',
                'api_url' => 'http://localhost:11334',
                'password' => env('RSPAMD_PASSWORD'),
            ],

            'amavisd' => [
                'name' => 'Amavisd-new',
                'handler' => \Ns\Mail\AntiSpam\AmavisEngine::class,
                'config_path' => '/etc/amavis',
                'socket' => '/var/lib/amavis/amavisd.sock',
            ],
        ],

        'default_engine' => env('ANTISPAM_ENGINE', 'rspamd'),
        'quarantine_enabled' => true,
        'quarantine_retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-Virus Configuration
    |--------------------------------------------------------------------------
    | Mail virus scanning settings
    */
    'antivirus' => [
        'enabled' => env('ANTIVIRUS_ENABLED', true),

        'engines' => [
            'clamav' => [
                'name' => 'ClamAV',
                'handler' => \Ns\Mail\AntiVirus\ClamAVEngine::class,
                'socket' => '/var/run/clamav/clamd.ctl',
                'config_path' => '/etc/clamav',
                'update_frequency' => 'daily',
            ],

            'f-prot' => [
                'name' => 'F-PROT',
                'handler' => \Ns\Mail\AntiVirus\FProtEngine::class,
                'command' => '/usr/local/f-prot/fpscan',
                'update_frequency' => 'daily',
            ],
        ],

        'default_engine' => env('ANTIVIRUS_ENGINE', 'clamav'),
        'quarantine_infected' => true,
        'notify_admin' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | DKIM Configuration
    |--------------------------------------------------------------------------
    | DomainKeys Identified Mail settings
    */
    'dkim' => [
        'enabled' => env('DKIM_ENABLED', true),
        'key_size' => env('DKIM_KEY_SIZE', 2048),
        'key_path' => env('DKIM_KEY_PATH', '/etc/opendkim/keys'),
        'selector' => env('DKIM_SELECTOR', 'mail'),
        'canonicalization' => 'relaxed/relaxed',
        'sign_headers' => [
            'from', 'sender', 'reply-to', 'subject', 'date',
            'message-id', 'to', 'cc', 'mime-version', 'content-type',
            'content-transfer-encoding', 'list-id', 'list-help',
            'list-unsubscribe', 'list-subscribe', 'list-post',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SPF Configuration
    |--------------------------------------------------------------------------
    | Sender Policy Framework settings
    */
    'spf' => [
        'enabled' => env('SPF_ENABLED', true),
        'default_policy' => env('SPF_DEFAULT_POLICY', 'v=spf1 mx a -all'),
        'include_mx' => true,
        'include_a' => true,
        'include_ip4' => [],
        'include_ip6' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | DMARC Configuration
    |--------------------------------------------------------------------------
    | Domain-based Message Authentication settings
    */
    'dmarc' => [
        'enabled' => env('DMARC_ENABLED', true),
        'default_policy' => env('DMARC_POLICY', 'quarantine'),
        'subdomain_policy' => env('DMARC_SUBDOMAIN_POLICY', 'quarantine'),
        'percentage' => env('DMARC_PERCENTAGE', 100),
        'rua_email' => env('DMARC_RUA_EMAIL'),
        'ruf_email' => env('DMARC_RUF_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Queue Configuration
    |--------------------------------------------------------------------------
    | Queue processing and monitoring settings
    */
    'queue' => [
        'monitor_queues' => [
            'active' => '/var/spool/postfix/active',
            'deferred' => '/var/spool/postfix/deferred',
            'hold' => '/var/spool/postfix/hold',
            'bounce' => '/var/spool/postfix/bounce',
            'corrupt' => '/var/spool/postfix/corrupt',
        ],

        'alert_thresholds' => [
            'deferred_count' => 100,
            'bounce_count' => 50,
            'queue_age_hours' => 4,
        ],

        'cleanup_schedule' => '0 2 * * *', // Daily at 2 AM
        'retention_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Logging Configuration
    |--------------------------------------------------------------------------
    | Log processing and analysis settings
    */
    'logging' => [
        'enabled' => env('MAIL_LOGGING_ENABLED', true),
        'log_files' => [
            'postfix' => '/var/log/mail.log',
            'dovecot' => '/var/log/dovecot.log',
            'rspamd' => '/var/log/rspamd/rspamd.log',
            'clamav' => '/var/log/clamav/clamav.log',
        ],

        'parse_frequency' => '*/5 * * * *', // Every 5 minutes
        'retention_days' => 90,
        'alert_patterns' => [
            'authentication_failure' => '/authentication failed|login failed/i',
            'relay_denied' => '/relay access denied|relay denied/i',
            'virus_detected' => '/virus detected|infected/i',
            'spam_detected' => '/spam detected|marked as spam/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    | Mail data backup settings
    */
    'backup' => [
        'enabled' => env('MAIL_BACKUP_ENABLED', true),
        'backup_path' => env('MAIL_BACKUP_PATH', '/var/backups/mail'),
        'schedule' => '0 1 * * *', // Daily at 1 AM
        'retention_days' => 30,
        'compress' => true,
        'include_configs' => true,
        'exclude_patterns' => [
            '*.tmp',
            '*.lock',
            'Trash/*',
            'Junk/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL/TLS Configuration
    |--------------------------------------------------------------------------
    | Mail server encryption settings
    */
    'ssl' => [
        'enabled' => env('MAIL_SSL_ENABLED', true),
        'cert_path' => env('MAIL_SSL_CERT_PATH', '/etc/ssl/certs'),
        'key_path' => env('MAIL_SSL_KEY_PATH', '/etc/ssl/private'),
        'protocols' => ['TLSv1.2', 'TLSv1.3'],
        'ciphers' => 'EECDH+AESGCM:EDH+AESGCM',
        'require_tls' => false,
        'auto_renew' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    | Mail server performance optimization
    */
    'performance' => [
        'connection_limits' => [
            'smtp_max_connections' => 100,
            'imap_max_connections' => 200,
            'pop3_max_connections' => 50,
        ],

        'rate_limits' => [
            'messages_per_minute' => 60,
            'recipients_per_message' => 50,
            'message_size_limit' => '25M',
        ],

        'caching' => [
            'enable_imap_cache' => true,
            'enable_auth_cache' => true,
            'cache_ttl_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    | Mail service monitoring settings
    */
    'monitoring' => [
        'enabled' => env('MAIL_MONITORING_ENABLED', true),
        'check_interval_minutes' => 5,
        'services_to_monitor' => [
            'postfix' => ['smtp:25', 'submission:587', 'smtps:465'],
            'dovecot' => ['imap:143', 'imaps:993', 'pop3:110', 'pop3s:995'],
            'rspamd' => ['http:11334'],
            'clamav' => ['unix:/var/run/clamav/clamd.ctl'],
        ],

        'health_checks' => [
            'queue_size' => true,
            'disk_space' => true,
            'memory_usage' => true,
            'certificate_expiry' => true,
        ],

        'alert_channels' => ['email', 'slack'],
        'alert_recipients' => [
            env('MAIL_ADMIN_EMAIL', 'admin@localhost'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    | Default values for new mail domains and mailboxes
    */
    'defaults' => [
        'mailbox_quota' => '1G',
        'domain_quota' => '10G',
        'message_size_limit' => '25M',
        'mailbox_format' => 'maildir',
        'enable_sieve' => true,
        'enable_imap' => true,
        'enable_pop3' => false,
        'password_scheme' => 'ARGON2I',
        'min_password_length' => 8,
    ],
];
