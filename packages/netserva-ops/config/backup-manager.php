<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Backup Repository
    |--------------------------------------------------------------------------
    | The default backup repository to use when none is specified
    */
    'default_repository' => env('BACKUP_DEFAULT_REPOSITORY', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Backup Repositories
    |--------------------------------------------------------------------------
    | Supported storage backends for backup repositories
    */
    'repositories' => [
        'local' => [
            'driver' => 'local',
            'root' => env('BACKUP_LOCAL_ROOT', storage_path('backups')),
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('BACKUP_S3_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'sftp' => [
            'driver' => 'sftp',
            'host' => env('BACKUP_SFTP_HOST'),
            'username' => env('BACKUP_SFTP_USERNAME'),
            'password' => env('BACKUP_SFTP_PASSWORD'),
            'privateKey' => env('BACKUP_SFTP_PRIVATE_KEY'),
            'passphrase' => env('BACKUP_SFTP_PASSPHRASE'),
            'root' => env('BACKUP_SFTP_ROOT', '/backups'),
            'port' => env('BACKUP_SFTP_PORT', 22),
            'timeout' => 30,
        ],

        'restic' => [
            'driver' => 'restic',
            'repository' => env('RESTIC_REPOSITORY'),
            'password' => env('RESTIC_PASSWORD'),
            'aws_access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'aws_secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'compression' => env('RESTIC_COMPRESSION', 'auto'),
            'cache_dir' => env('RESTIC_CACHE_DIR', '/tmp/restic-cache'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Retention Policies
    |--------------------------------------------------------------------------
    | Default backup retention settings
    */
    'retention' => [
        'keep_daily' => env('BACKUP_KEEP_DAILY', 7),
        'keep_weekly' => env('BACKUP_KEEP_WEEKLY', 4),
        'keep_monthly' => env('BACKUP_KEEP_MONTHLY', 6),
        'keep_yearly' => env('BACKUP_KEEP_YEARLY', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Types
    |--------------------------------------------------------------------------
    | Supported backup data types and their handlers
    */
    'backup_types' => [
        'database' => [
            'handler' => \NetServa\Ops\Handlers\DatabaseBackupHandler::class,
            'supported_engines' => ['mysql', 'postgresql', 'sqlite'],
        ],

        'files' => [
            'handler' => \NetServa\Ops\Handlers\FileBackupHandler::class,
            'compression' => 'gzip',
            'exclude_patterns' => [
                '*.log',
                'tmp/*',
                'cache/*',
                '.git/*',
            ],
        ],

        'docker' => [
            'handler' => \NetServa\Ops\Handlers\DockerBackupHandler::class,
            'include_volumes' => true,
            'include_images' => false,
        ],

        'lxc' => [
            'handler' => \NetServa\Ops\Handlers\LxcBackupHandler::class,
            'compression' => 'gzip',
            'include_snapshots' => true,
        ],

        'system' => [
            'handler' => \NetServa\Ops\Handlers\SystemBackupHandler::class,
            'include_configs' => [
                '/etc',
                '/usr/local/etc',
                '/opt/*/etc',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    | Backup encryption settings
    */
    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
        'method' => env('BACKUP_ENCRYPTION_METHOD', 'aes-256-cbc'),
        'key_source' => env('BACKUP_KEY_SOURCE', 'secrets_manager'), // secrets_manager, env, file
        'key_name' => env('BACKUP_ENCRYPTION_KEY_NAME', 'backup-encryption-key'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    | Default compression settings
    */
    'compression' => [
        'enabled' => env('BACKUP_COMPRESSION_ENABLED', true),
        'method' => env('BACKUP_COMPRESSION_METHOD', 'gzip'), // gzip, bzip2, xz
        'level' => env('BACKUP_COMPRESSION_LEVEL', 6), // 1-9
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    | Backup monitoring and alerting
    */
    'monitoring' => [
        'enabled' => env('BACKUP_MONITORING_ENABLED', true),
        'alert_on_failure' => env('BACKUP_ALERT_ON_FAILURE', true),
        'alert_on_missing' => env('BACKUP_ALERT_ON_MISSING', true),
        'health_check_url' => env('BACKUP_HEALTH_CHECK_URL'),
        'max_runtime_minutes' => env('BACKUP_MAX_RUNTIME', 240), // 4 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallelization
    |--------------------------------------------------------------------------
    | Backup job parallelization settings
    */
    'parallelization' => [
        'enabled' => env('BACKUP_PARALLEL_ENABLED', true),
        'max_concurrent_jobs' => env('BACKUP_MAX_CONCURRENT', 3),
        'queue_connection' => env('BACKUP_QUEUE_CONNECTION', 'database'),
        'queue_name' => env('BACKUP_QUEUE_NAME', 'backups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Schedules
    |--------------------------------------------------------------------------
    | Common backup schedule templates
    */
    'schedule_templates' => [
        'daily' => [
            'name' => 'Daily Backup',
            'cron_expression' => '0 2 * * *', // 2 AM daily
            'timezone' => 'UTC',
        ],
        'weekly' => [
            'name' => 'Weekly Backup',
            'cron_expression' => '0 3 * * 0', // 3 AM Sunday
            'timezone' => 'UTC',
        ],
        'monthly' => [
            'name' => 'Monthly Backup',
            'cron_expression' => '0 4 1 * *', // 4 AM first day of month
            'timezone' => 'UTC',
        ],
    ],
];
