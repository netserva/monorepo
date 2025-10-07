<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Management Settings
    |--------------------------------------------------------------------------
    | Core configuration management settings and behavior
    */
    'enabled' => env('CONFIG_MANAGER_ENABLED', true),

    'default_template_engine' => env('CONFIG_TEMPLATE_ENGINE', 'twig'),

    'config_storage_path' => env('CONFIG_STORAGE_PATH', storage_path('app/configs')),

    'backup_retention_days' => env('CONFIG_BACKUP_RETENTION', 30),

    /*
    |--------------------------------------------------------------------------
    | Template Engines
    |--------------------------------------------------------------------------
    | Available template engines for configuration files
    */
    'template_engines' => [
        'twig' => [
            'name' => 'Twig Templates',
            'handler' => \Ns\Config\Engines\TwigTemplateEngine::class,
            'extensions' => ['twig', 'j2'],
        ],

        'blade' => [
            'name' => 'Laravel Blade',
            'handler' => \Ns\Config\Engines\BladeTemplateEngine::class,
            'extensions' => ['blade.php'],
        ],

        'mustache' => [
            'name' => 'Mustache Templates',
            'handler' => \Ns\Config\Engines\MustacheTemplateEngine::class,
            'extensions' => ['mustache'],
        ],

        'simple' => [
            'name' => 'Simple Variable Substitution',
            'handler' => \Ns\Config\Engines\SimpleTemplateEngine::class,
            'extensions' => ['template', 'tmpl'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Types
    |--------------------------------------------------------------------------
    | Supported configuration file types and their handlers
    */
    'config_types' => [
        'nginx' => [
            'name' => 'Nginx Configuration',
            'handler' => \Ns\Config\Handlers\NginxConfigHandler::class,
            'validator' => \Ns\Config\Validators\NginxConfigValidator::class,
            'extensions' => ['conf'],
            'syntax_check_command' => 'nginx -t -c {file}',
        ],

        'apache' => [
            'name' => 'Apache Configuration',
            'handler' => \Ns\Config\Handlers\ApacheConfigHandler::class,
            'validator' => \Ns\Config\Validators\ApacheConfigValidator::class,
            'extensions' => ['conf'],
            'syntax_check_command' => 'apache2ctl -t -f {file}',
        ],

        'php' => [
            'name' => 'PHP Configuration',
            'handler' => \Ns\Config\Handlers\PhpConfigHandler::class,
            'validator' => \Ns\Config\Validators\PhpConfigValidator::class,
            'extensions' => ['ini'],
            'syntax_check_command' => 'php -t -c {file}',
        ],

        'mysql' => [
            'name' => 'MySQL Configuration',
            'handler' => \Ns\Config\Handlers\MysqlConfigHandler::class,
            'validator' => \Ns\Config\Validators\MysqlConfigValidator::class,
            'extensions' => ['cnf'],
            'syntax_check_command' => 'mysqld --help --verbose --skip-networking',
        ],

        'postfix' => [
            'name' => 'Postfix Configuration',
            'handler' => \Ns\Config\Handlers\PostfixConfigHandler::class,
            'validator' => \Ns\Config\Validators\PostfixConfigValidator::class,
            'extensions' => ['cf'],
            'syntax_check_command' => 'postconf -c {directory}',
        ],

        'dovecot' => [
            'name' => 'Dovecot Configuration',
            'handler' => \Ns\Config\Handlers\DovecotConfigHandler::class,
            'validator' => \Ns\Config\Validators\DovecotConfigValidator::class,
            'extensions' => ['conf'],
            'syntax_check_command' => 'dovecot -n -c {file}',
        ],

        'systemd' => [
            'name' => 'Systemd Service',
            'handler' => \Ns\Config\Handlers\SystemdConfigHandler::class,
            'validator' => \Ns\Config\Validators\SystemdConfigValidator::class,
            'extensions' => ['service', 'timer', 'socket'],
            'syntax_check_command' => 'systemd-analyze verify {file}',
        ],

        'yaml' => [
            'name' => 'YAML Configuration',
            'handler' => \Ns\Config\Handlers\YamlConfigHandler::class,
            'validator' => \Ns\Config\Validators\YamlConfigValidator::class,
            'extensions' => ['yml', 'yaml'],
            'syntax_check_command' => null, // Built-in YAML parser
        ],

        'json' => [
            'name' => 'JSON Configuration',
            'handler' => \Ns\Config\Handlers\JsonConfigHandler::class,
            'validator' => \Ns\Config\Validators\JsonConfigValidator::class,
            'extensions' => ['json'],
            'syntax_check_command' => null, // Built-in JSON parser
        ],

        'ini' => [
            'name' => 'INI Configuration',
            'handler' => \Ns\Config\Handlers\IniConfigHandler::class,
            'validator' => \Ns\Config\Validators\IniConfigValidator::class,
            'extensions' => ['ini', 'cfg'],
            'syntax_check_command' => null, // Built-in INI parser
        ],

        'shell' => [
            'name' => 'Shell Script',
            'handler' => \Ns\Config\Handlers\ShellConfigHandler::class,
            'validator' => \Ns\Config\Validators\ShellConfigValidator::class,
            'extensions' => ['sh', 'bash'],
            'syntax_check_command' => 'bash -n {file}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Methods
    |--------------------------------------------------------------------------
    | Available methods for deploying configuration files
    */
    'deployment_methods' => [
        'ssh' => [
            'name' => 'SSH Deploy',
            'handler' => \Ns\Config\Deployers\SshDeployer::class,
            'supports_backup' => true,
            'supports_rollback' => true,
        ],

        'rsync' => [
            'name' => 'Rsync Deploy',
            'handler' => \Ns\Config\Deployers\RsyncDeployer::class,
            'supports_backup' => true,
            'supports_rollback' => true,
        ],

        'local' => [
            'name' => 'Local Deploy',
            'handler' => \Ns\Config\Deployers\LocalDeployer::class,
            'supports_backup' => true,
            'supports_rollback' => true,
        ],

        'ansible' => [
            'name' => 'Ansible Deploy',
            'handler' => \Ns\Config\Deployers\AnsibleDeployer::class,
            'supports_backup' => true,
            'supports_rollback' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variable Sources
    |--------------------------------------------------------------------------
    | Sources for configuration variables
    */
    'variable_sources' => [
        'database' => [
            'name' => 'Database Variables',
            'handler' => \Ns\Config\VariableSources\DatabaseSource::class,
            'priority' => 10,
        ],

        'environment' => [
            'name' => 'Environment Variables',
            'handler' => \Ns\Config\VariableSources\EnvironmentSource::class,
            'priority' => 20,
        ],

        'file' => [
            'name' => 'File Variables',
            'handler' => \Ns\Config\VariableSources\FileSource::class,
            'priority' => 30,
        ],

        'vault' => [
            'name' => 'HashiCorp Vault',
            'handler' => \Ns\Config\VariableSources\VaultSource::class,
            'priority' => 5,
        ],

        'secrets_manager' => [
            'name' => 'NS Secrets Manager',
            'handler' => \Ns\Config\VariableSources\SecretsManagerSource::class,
            'priority' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    | Global validation rules for configuration management
    */
    'validation_rules' => [
        'require_backup_before_deploy' => true,
        'require_syntax_validation' => true,
        'require_approval_for_production' => true,
        'max_template_size_mb' => 10,
        'allowed_file_extensions' => [
            'conf', 'cfg', 'ini', 'yml', 'yaml', 'json', 'xml',
            'sh', 'bash', 'service', 'timer', 'socket', 'cnf',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    | Security configurations for config management
    */
    'security' => [
        'encrypt_sensitive_variables' => true,
        'mask_secrets_in_logs' => true,
        'require_authentication' => true,
        'audit_all_changes' => true,
        'allowed_template_functions' => [
            // Twig functions that are safe to use
            'date', 'format', 'upper', 'lower', 'title', 'trim',
            'replace', 'split', 'join', 'length', 'default',
            'escape', 'raw', 'urlencode', 'base64_encode',
        ],
        'blocked_template_functions' => [
            // Dangerous functions to block
            'exec', 'system', 'shell_exec', 'passthru', 'eval',
            'file_get_contents', 'file_put_contents', 'include', 'require',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    | Notification channels for deployment events
    */
    'notifications' => [
        'enabled' => env('CONFIG_NOTIFICATIONS_ENABLED', true),

        'channels' => [
            'slack' => [
                'webhook_url' => env('CONFIG_SLACK_WEBHOOK'),
                'channel' => env('CONFIG_SLACK_CHANNEL', '#deployments'),
            ],

            'email' => [
                'to' => env('CONFIG_EMAIL_NOTIFICATIONS'),
            ],

            'webhook' => [
                'url' => env('CONFIG_WEBHOOK_URL'),
                'method' => 'POST',
            ],
        ],

        'events' => [
            'deployment_started' => ['slack', 'email'],
            'deployment_success' => ['slack'],
            'deployment_failed' => ['slack', 'email', 'webhook'],
            'rollback_performed' => ['slack', 'email'],
            'validation_failed' => ['email'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Git Integration
    |--------------------------------------------------------------------------
    | Git repository settings for configuration versioning
    */
    'git' => [
        'enabled' => env('CONFIG_GIT_ENABLED', false),
        'repository_path' => env('CONFIG_GIT_REPO', storage_path('app/config-repo')),
        'auto_commit' => true,
        'auto_push' => false,
        'commit_message_template' => 'Config update: {template_name} on {infrastructure_node}',
        'branch' => 'main',
        'user_name' => env('CONFIG_GIT_USER', 'NS Config Manager'),
        'user_email' => env('CONFIG_GIT_EMAIL', 'config@ns.local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    | Performance optimization settings
    */
    'performance' => [
        'enable_template_caching' => true,
        'template_cache_ttl' => 3600, // 1 hour
        'enable_variable_caching' => true,
        'variable_cache_ttl' => 300, // 5 minutes
        'parallel_deployments' => false,
        'max_concurrent_deployments' => 3,
    ],
];
