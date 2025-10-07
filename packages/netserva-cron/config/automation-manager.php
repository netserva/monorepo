<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automation Manager Configuration
    |--------------------------------------------------------------------------
    | Task orchestration and workflow automation settings
    */
    'enabled' => env('AUTOMATION_MANAGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Workflow Engine Configuration
    |--------------------------------------------------------------------------
    | Core workflow execution engine settings
    */
    'workflow_engine' => [
        'enabled' => env('WORKFLOW_ENGINE_ENABLED', true),
        'max_concurrent_workflows' => env('MAX_CONCURRENT_WORKFLOWS', 50),
        'max_workflow_execution_time' => env('MAX_WORKFLOW_EXECUTION_TIME', 3600), // seconds
        'workflow_timeout_action' => env('WORKFLOW_TIMEOUT_ACTION', 'terminate'), // terminate, pause, retry
        'enable_workflow_versioning' => true,
        'enable_workflow_rollback' => true,
        'auto_cleanup_completed' => true,
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Execution Configuration
    |--------------------------------------------------------------------------
    | Individual task execution settings
    */
    'task_execution' => [
        'default_timeout' => 300, // seconds
        'max_retries' => 3,
        'retry_delay' => 30, // seconds
        'retry_backoff' => 'exponential', // linear, exponential, fixed
        'max_retry_delay' => 300, // seconds
        'parallel_execution' => true,
        'max_parallel_tasks' => 10,
        'task_isolation' => 'process', // thread, process, container
        'enable_task_logging' => true,
        'log_task_output' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    | Cron-based task scheduling settings
    */
    'scheduling' => [
        'enabled' => env('AUTOMATION_SCHEDULING_ENABLED', true),
        'timezone' => env('AUTOMATION_TIMEZONE', 'UTC'),
        'max_scheduled_jobs' => 1000,
        'schedule_resolution' => 60, // seconds - minimum scheduling granularity
        'missed_job_threshold' => 300, // seconds - when to consider a job "missed"
        'missed_job_action' => 'run_immediately', // skip, run_immediately, queue
        'overlap_prevention' => true,
        'schedule_persistence' => 'database', // database, file, memory
    ],

    /*
    |--------------------------------------------------------------------------
    | Trigger System Configuration
    |--------------------------------------------------------------------------
    | Event-based workflow triggering
    */
    'triggers' => [
        'enabled' => true,
        'max_trigger_listeners' => 100,
        'trigger_evaluation_timeout' => 30, // seconds
        'enable_trigger_conditions' => true,
        'condition_evaluation_engine' => 'php', // php, lua, javascript

        'supported_trigger_types' => [
            'time_based' => [
                'name' => 'Time-based Triggers',
                'handler' => \Ns\Automation\Triggers\TimeBasedTrigger::class,
                'enabled' => true,
            ],
            'file_system' => [
                'name' => 'File System Events',
                'handler' => \Ns\Automation\Triggers\FileSystemTrigger::class,
                'enabled' => true,
            ],
            'database' => [
                'name' => 'Database Events',
                'handler' => \Ns\Automation\Triggers\DatabaseTrigger::class,
                'enabled' => true,
            ],
            'webhook' => [
                'name' => 'Webhook Triggers',
                'handler' => \Ns\Automation\Triggers\WebhookTrigger::class,
                'enabled' => true,
            ],
            'service_status' => [
                'name' => 'Service Status Changes',
                'handler' => \Ns\Automation\Triggers\ServiceStatusTrigger::class,
                'enabled' => true,
            ],
            'metric_threshold' => [
                'name' => 'Metric Threshold Events',
                'handler' => \Ns\Automation\Triggers\MetricThresholdTrigger::class,
                'enabled' => true,
            ],
            'backup_completion' => [
                'name' => 'Backup Completion Events',
                'handler' => \Ns\Automation\Triggers\BackupCompletionTrigger::class,
                'enabled' => true,
            ],
            'ssl_expiration' => [
                'name' => 'SSL Certificate Expiration',
                'handler' => \Ns\Automation\Triggers\SslExpirationTrigger::class,
                'enabled' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Types Configuration
    |--------------------------------------------------------------------------
    | Available task types and their handlers
    */
    'task_types' => [
        'shell_command' => [
            'name' => 'Shell Command',
            'handler' => \Ns\Automation\Tasks\ShellCommandTask::class,
            'timeout' => 300,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'ssh_command' => [
            'name' => 'SSH Remote Command',
            'handler' => \Ns\Automation\Tasks\SshCommandTask::class,
            'timeout' => 600,
            'allowed_in_production' => true,
            'requires_approval' => true,
        ],
        'database_query' => [
            'name' => 'Database Query',
            'handler' => \Ns\Automation\Tasks\DatabaseQueryTask::class,
            'timeout' => 300,
            'allowed_in_production' => false,
            'requires_approval' => true,
        ],
        'file_operation' => [
            'name' => 'File Operation',
            'handler' => \Ns\Automation\Tasks\FileOperationTask::class,
            'timeout' => 120,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'service_control' => [
            'name' => 'Service Control',
            'handler' => \Ns\Automation\Tasks\ServiceControlTask::class,
            'timeout' => 60,
            'allowed_in_production' => true,
            'requires_approval' => true,
        ],
        'backup_operation' => [
            'name' => 'Backup Operation',
            'handler' => \Ns\Automation\Tasks\BackupOperationTask::class,
            'timeout' => 3600,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'ssl_renewal' => [
            'name' => 'SSL Certificate Renewal',
            'handler' => \Ns\Automation\Tasks\SslRenewalTask::class,
            'timeout' => 300,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'dns_update' => [
            'name' => 'DNS Record Update',
            'handler' => \Ns\Automation\Tasks\DnsUpdateTask::class,
            'timeout' => 120,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'email_notification' => [
            'name' => 'Email Notification',
            'handler' => \Ns\Automation\Tasks\EmailNotificationTask::class,
            'timeout' => 60,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'webhook_call' => [
            'name' => 'Webhook Call',
            'handler' => \Ns\Automation\Tasks\WebhookCallTask::class,
            'timeout' => 60,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'condition_check' => [
            'name' => 'Condition Check',
            'handler' => \Ns\Automation\Tasks\ConditionCheckTask::class,
            'timeout' => 30,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
        'wait_delay' => [
            'name' => 'Wait/Delay',
            'handler' => \Ns\Automation\Tasks\WaitDelayTask::class,
            'timeout' => 3600,
            'allowed_in_production' => true,
            'requires_approval' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    | Background job processing settings
    */
    'queue' => [
        'connection' => env('AUTOMATION_QUEUE_CONNECTION', 'database'),
        'queue_name' => env('AUTOMATION_QUEUE_NAME', 'automation'),
        'max_workers' => env('AUTOMATION_MAX_WORKERS', 5),
        'worker_timeout' => env('AUTOMATION_WORKER_TIMEOUT', 3600),
        'max_job_retries' => 3,
        'failed_job_retention' => 7, // days
        'enable_job_batching' => true,
        'batch_size' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables and Context
    |--------------------------------------------------------------------------
    | Workflow variable and context management
    */
    'variables' => [
        'enable_global_variables' => true,
        'enable_workflow_variables' => true,
        'enable_task_variables' => true,
        'enable_runtime_variables' => true,
        'variable_encryption' => true,
        'max_variable_size' => 1048576, // 1MB in bytes
        'enable_variable_versioning' => true,
        'variable_retention_days' => 90,

        'built_in_variables' => [
            'system' => [
                'hostname' => '{{ system.hostname }}',
                'timestamp' => '{{ system.timestamp }}',
                'date' => '{{ system.date }}',
                'user' => '{{ system.user }}',
                'environment' => '{{ system.environment }}',
            ],
            'workflow' => [
                'id' => '{{ workflow.id }}',
                'name' => '{{ workflow.name }}',
                'version' => '{{ workflow.version }}',
                'started_at' => '{{ workflow.started_at }}',
                'execution_id' => '{{ workflow.execution_id }}',
            ],
            'task' => [
                'id' => '{{ task.id }}',
                'name' => '{{ task.name }}',
                'type' => '{{ task.type }}',
                'attempt' => '{{ task.attempt }}',
                'output' => '{{ task.output }}',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    | Security and access control settings
    */
    'security' => [
        'enable_approval_workflow' => env('AUTOMATION_REQUIRE_APPROVAL', true),
        'default_approval_required' => false,
        'approval_timeout' => 3600, // seconds
        'enable_execution_logging' => true,
        'log_sensitive_data' => false,
        'enable_sandbox_mode' => env('AUTOMATION_SANDBOX_MODE', false),
        'allowed_commands' => [],
        'blocked_commands' => ['rm -rf', 'sudo rm', 'mkfs', 'dd if='],
        'allowed_file_paths' => [],
        'blocked_file_paths' => ['/etc/passwd', '/etc/shadow', '/root/.ssh'],
        'enable_resource_limits' => true,
        'max_cpu_usage' => 50, // percent
        'max_memory_usage' => 512, // MB
        'max_disk_usage' => 1024, // MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    | Monitoring and notification settings
    */
    'monitoring' => [
        'enabled' => true,
        'metrics_collection' => true,
        'performance_tracking' => true,
        'error_tracking' => true,
        'success_rate_tracking' => true,
        'execution_time_tracking' => true,
        'resource_usage_tracking' => true,

        'alert_thresholds' => [
            'workflow_failure_rate' => 10, // percent
            'task_failure_rate' => 15, // percent
            'average_execution_time' => 300, // seconds
            'queue_depth' => 100, // number of jobs
            'worker_utilization' => 80, // percent
        ],

        'notification_channels' => [
            'email' => [
                'enabled' => true,
                'recipients' => env('AUTOMATION_ALERT_EMAILS', ''),
            ],
            'slack' => [
                'enabled' => false,
                'webhook_url' => env('AUTOMATION_SLACK_WEBHOOK'),
                'channel' => env('AUTOMATION_SLACK_CHANNEL', '#automation'),
            ],
            'webhook' => [
                'enabled' => false,
                'url' => env('AUTOMATION_WEBHOOK_URL'),
                'headers' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    | External system integration configuration
    */
    'integrations' => [
        'ns_infrastructure' => [
            'enabled' => true,
            'auto_discover_nodes' => true,
            'sync_node_status' => true,
        ],
        'ns_monitoring' => [
            'enabled' => true,
            'trigger_on_alerts' => true,
            'metric_based_triggers' => true,
        ],
        'ns_backup' => [
            'enabled' => true,
            'auto_schedule_backups' => true,
            'backup_completion_triggers' => true,
        ],
        'ns_ssl' => [
            'enabled' => true,
            'auto_renewal_workflows' => true,
            'expiration_triggers' => true,
        ],
        'ns_dns' => [
            'enabled' => true,
            'auto_dns_updates' => true,
            'failover_automation' => true,
        ],
        'external_apis' => [
            'enabled' => true,
            'rate_limiting' => true,
            'timeout' => 30, // seconds
            'retry_attempts' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    | Development and testing configuration
    */
    'development' => [
        'enable_debug_mode' => env('AUTOMATION_DEBUG', false),
        'dry_run_mode' => env('AUTOMATION_DRY_RUN', false),
        'workflow_simulation' => env('AUTOMATION_SIMULATE', false),
        'enable_test_mode' => env('AUTOMATION_TEST_MODE', false),
        'mock_external_calls' => env('AUTOMATION_MOCK_EXTERNAL', false),
        'verbose_logging' => env('AUTOMATION_VERBOSE_LOGS', false),
        'enable_profiling' => env('AUTOMATION_PROFILING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Templates
    |--------------------------------------------------------------------------
    | Pre-built workflow templates for common tasks
    */
    'workflow_templates' => [
        'server_maintenance' => [
            'name' => 'Server Maintenance Workflow',
            'description' => 'Complete server maintenance including updates, cleanup, and restart',
            'template_file' => 'workflows/server_maintenance.json',
            'category' => 'maintenance',
            'requires_approval' => true,
        ],
        'ssl_renewal' => [
            'name' => 'SSL Certificate Renewal',
            'description' => 'Automatic SSL certificate renewal and deployment',
            'template_file' => 'workflows/ssl_renewal.json',
            'category' => 'security',
            'requires_approval' => false,
        ],
        'backup_rotation' => [
            'name' => 'Backup Rotation Workflow',
            'description' => 'Automated backup creation and old backup cleanup',
            'template_file' => 'workflows/backup_rotation.json',
            'category' => 'backup',
            'requires_approval' => false,
        ],
        'incident_response' => [
            'name' => 'Incident Response Workflow',
            'description' => 'Automated incident response and recovery procedures',
            'template_file' => 'workflows/incident_response.json',
            'category' => 'incident',
            'requires_approval' => true,
        ],
        'deployment_pipeline' => [
            'name' => 'Application Deployment',
            'description' => 'Automated application deployment with rollback capability',
            'template_file' => 'workflows/deployment_pipeline.json',
            'category' => 'deployment',
            'requires_approval' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    | Performance optimization settings
    */
    'performance' => [
        'enable_caching' => true,
        'cache_ttl' => 3600, // seconds
        'enable_result_caching' => true,
        'enable_query_optimization' => true,
        'batch_database_operations' => true,
        'optimize_workflow_execution' => true,
        'enable_lazy_loading' => true,
        'prefetch_related_data' => true,
        'connection_pooling' => true,
        'max_connections' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Configuration Values
    |--------------------------------------------------------------------------
    | Default values for new workflows and tasks
    */
    'defaults' => [
        'workflow_timeout' => 1800, // 30 minutes
        'task_timeout' => 300, // 5 minutes
        'max_retries' => 3,
        'retry_delay' => 30, // seconds
        'log_level' => 'info',
        'notification_level' => 'error',
        'execution_environment' => 'production',
        'priority' => 'normal',
        'concurrency_limit' => 5,
        'enable_monitoring' => true,
        'enable_logging' => true,
    ],
];
