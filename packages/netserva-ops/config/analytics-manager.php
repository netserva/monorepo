<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Manager Configuration
    |--------------------------------------------------------------------------
    | Reporting and analytics management settings
    */
    'enabled' => env('ANALYTICS_MANAGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Data Sources Configuration
    |--------------------------------------------------------------------------
    | Available data sources for analytics and reporting
    */
    'data_sources' => [
        'ns_infrastructure' => [
            'name' => 'Infrastructure Manager',
            'enabled' => true,
            'connection' => 'default',
            'models' => [
                'infrastructure_nodes',
                'infrastructure_providers',
                'infrastructure_environments',
            ],
            'metrics' => ['node_count', 'uptime', 'resource_utilization'],
        ],
        'ns_monitoring' => [
            'name' => 'Monitoring Manager',
            'enabled' => true,
            'connection' => 'default',
            'models' => [
                'monitoring_checks',
                'monitoring_alerts',
                'monitoring_metrics',
            ],
            'metrics' => ['alert_count', 'response_time', 'availability'],
        ],
        'ns_ssl' => [
            'name' => 'SSL Manager',
            'enabled' => true,
            'connection' => 'default',
            'models' => [
                'ssl_certificates',
                'ssl_domains',
                'ssl_validations',
            ],
            'metrics' => ['certificate_count', 'expiry_warnings', 'validation_success'],
        ],
        'ns_backup' => [
            'name' => 'Backup Manager',
            'enabled' => true,
            'connection' => 'default',
            'models' => [
                'backup_jobs',
                'backup_schedules',
                'backup_storages',
            ],
            'metrics' => ['backup_success_rate', 'storage_usage', 'recovery_time'],
        ],
        'ns_automation' => [
            'name' => 'Automation Manager',
            'enabled' => true,
            'connection' => 'default',
            'models' => [
                'automation_workflows',
                'automation_jobs',
                'automation_schedules',
            ],
            'metrics' => ['workflow_success_rate', 'execution_time', 'automation_count'],
        ],
        'ns_compliance' => [
            'name' => 'Compliance Manager',
            'enabled' => true,
            'connection' => 'default',
            'models' => [
                'compliance_frameworks',
                'compliance_controls',
                'security_incidents',
            ],
            'metrics' => ['compliance_score', 'incident_count', 'control_effectiveness'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    | Report generation and management settings
    */
    'reporting' => [
        'enabled' => true,
        'scheduled_reports' => true,
        'real_time_reports' => true,
        'cached_reports' => true,
        'cache_ttl' => 3600, // seconds
        'max_report_size' => 50, // MB
        'concurrent_generation_limit' => 5,

        'export_formats' => [
            'pdf' => [
                'name' => 'PDF',
                'enabled' => true,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'engine' => 'tcpdf',
            ],
            'excel' => [
                'name' => 'Excel',
                'enabled' => true,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => 'xlsx',
                'engine' => 'phpspreadsheet',
            ],
            'csv' => [
                'name' => 'CSV',
                'enabled' => true,
                'mime_type' => 'text/csv',
                'extension' => 'csv',
                'engine' => 'league/csv',
            ],
            'json' => [
                'name' => 'JSON',
                'enabled' => true,
                'mime_type' => 'application/json',
                'extension' => 'json',
                'engine' => 'native',
            ],
        ],

        'template_engines' => [
            'blade' => [
                'name' => 'Blade Templates',
                'enabled' => true,
                'file_extension' => '.blade.php',
            ],
            'twig' => [
                'name' => 'Twig Templates',
                'enabled' => false,
                'file_extension' => '.twig',
            ],
        ],

        'report_categories' => [
            'operational' => 'Operational Reports',
            'security' => 'Security Reports',
            'compliance' => 'Compliance Reports',
            'performance' => 'Performance Reports',
            'financial' => 'Financial Reports',
            'executive' => 'Executive Reports',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    | Dashboard and visualization settings
    */
    'dashboards' => [
        'enabled' => true,
        'real_time_updates' => true,
        'update_interval' => 30, // seconds
        'auto_refresh' => true,
        'responsive_design' => true,
        'print_friendly' => true,

        'widget_types' => [
            'chart' => [
                'name' => 'Chart Widget',
                'supported_types' => ['line', 'bar', 'pie', 'doughnut', 'area'],
                'max_data_points' => 1000,
            ],
            'metric' => [
                'name' => 'Metric Widget',
                'formats' => ['number', 'percentage', 'currency', 'duration'],
                'trend_indicators' => true,
            ],
            'table' => [
                'name' => 'Table Widget',
                'max_rows' => 100,
                'sorting' => true,
                'filtering' => true,
            ],
            'text' => [
                'name' => 'Text Widget',
                'markdown_support' => true,
                'html_support' => false,
            ],
            'gauge' => [
                'name' => 'Gauge Widget',
                'threshold_colors' => true,
                'animation' => true,
            ],
        ],

        'chart_libraries' => [
            'chartjs' => [
                'name' => 'Chart.js',
                'enabled' => true,
                'version' => '4.0',
            ],
            'apexcharts' => [
                'name' => 'ApexCharts',
                'enabled' => false,
                'version' => '3.0',
            ],
        ],

        'themes' => [
            'default' => [
                'name' => 'Default Theme',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#64748b',
            ],
            'dark' => [
                'name' => 'Dark Theme',
                'primary_color' => '#1e293b',
                'secondary_color' => '#475569',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics and KPIs
    |--------------------------------------------------------------------------
    | Key performance indicators and metrics configuration
    */
    'metrics' => [
        'collection_enabled' => true,
        'real_time_collection' => true,
        'batch_processing' => true,
        'data_retention_days' => 365,
        'aggregation_intervals' => ['1m', '5m', '15m', '1h', '1d', '1w', '1M'],

        'metric_categories' => [
            'infrastructure' => [
                'name' => 'Infrastructure Metrics',
                'metrics' => [
                    'node_availability' => 'Node Availability %',
                    'resource_utilization' => 'Resource Utilization %',
                    'network_latency' => 'Network Latency (ms)',
                    'storage_usage' => 'Storage Usage %',
                ],
            ],
            'security' => [
                'name' => 'Security Metrics',
                'metrics' => [
                    'security_incidents' => 'Security Incidents Count',
                    'vulnerability_count' => 'Open Vulnerabilities',
                    'compliance_score' => 'Compliance Score %',
                    'ssl_certificate_expiry' => 'SSL Certificates Expiring',
                ],
            ],
            'operational' => [
                'name' => 'Operational Metrics',
                'metrics' => [
                    'backup_success_rate' => 'Backup Success Rate %',
                    'automation_success_rate' => 'Automation Success Rate %',
                    'mean_time_to_recovery' => 'Mean Time to Recovery (hours)',
                    'change_success_rate' => 'Change Success Rate %',
                ],
            ],
            'performance' => [
                'name' => 'Performance Metrics',
                'metrics' => [
                    'response_time' => 'Average Response Time (ms)',
                    'throughput' => 'Throughput (req/sec)',
                    'error_rate' => 'Error Rate %',
                    'cpu_utilization' => 'CPU Utilization %',
                ],
            ],
        ],

        'aggregation_functions' => [
            'avg' => 'Average',
            'sum' => 'Sum',
            'min' => 'Minimum',
            'max' => 'Maximum',
            'count' => 'Count',
            'median' => 'Median',
            'percentile_95' => '95th Percentile',
            'percentile_99' => '99th Percentile',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Processing
    |--------------------------------------------------------------------------
    | Data processing and transformation settings
    */
    'data_processing' => [
        'enabled' => true,
        'batch_size' => 1000,
        'parallel_processing' => true,
        'max_workers' => 4,
        'queue_connection' => 'database',
        'queue_name' => 'analytics',

        'transformations' => [
            'data_cleaning' => true,
            'null_value_handling' => 'exclude',
            'outlier_detection' => true,
            'data_normalization' => false,
            'duplicate_removal' => true,
        ],

        'validation_rules' => [
            'data_type_validation' => true,
            'range_validation' => true,
            'format_validation' => true,
            'business_rule_validation' => true,
        ],

        'error_handling' => [
            'continue_on_error' => true,
            'log_errors' => true,
            'retry_failed_records' => true,
            'max_retries' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    | Analytics alerting and notification settings
    */
    'alerting' => [
        'enabled' => true,
        'real_time_alerts' => true,
        'threshold_alerts' => true,
        'trend_alerts' => true,
        'anomaly_detection' => false,

        'alert_channels' => [
            'email' => [
                'enabled' => true,
                'templates' => 'analytics.email',
            ],
            'slack' => [
                'enabled' => false,
                'webhook_url' => env('ANALYTICS_SLACK_WEBHOOK'),
            ],
            'webhook' => [
                'enabled' => false,
                'url' => env('ANALYTICS_WEBHOOK_URL'),
            ],
        ],

        'threshold_types' => [
            'absolute' => 'Absolute Value',
            'percentage' => 'Percentage Change',
            'rate' => 'Rate of Change',
            'relative' => 'Relative to Baseline',
        ],

        'alert_severities' => [
            'critical' => [
                'name' => 'Critical',
                'color' => '#dc2626',
                'escalation_minutes' => 15,
            ],
            'high' => [
                'name' => 'High',
                'color' => '#ea580c',
                'escalation_minutes' => 60,
            ],
            'medium' => [
                'name' => 'Medium',
                'color' => '#d97706',
                'escalation_minutes' => 240,
            ],
            'low' => [
                'name' => 'Low',
                'color' => '#65a30d',
                'escalation_minutes' => 1440,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    | Performance optimization settings
    */
    'performance' => [
        'caching_enabled' => true,
        'cache_driver' => 'redis',
        'cache_ttl' => 3600,
        'query_optimization' => true,
        'lazy_loading' => true,
        'pagination_enabled' => true,
        'default_page_size' => 50,
        'max_page_size' => 1000,

        'database_optimization' => [
            'read_replica_enabled' => false,
            'query_timeout' => 30,
            'connection_pooling' => true,
            'index_optimization' => true,
        ],

        'memory_management' => [
            'max_memory_limit' => '512M',
            'garbage_collection' => true,
            'memory_monitoring' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    | Analytics security and access control
    */
    'security' => [
        'access_control_enabled' => true,
        'role_based_access' => true,
        'data_encryption' => false,
        'audit_logging' => true,
        'sensitive_data_masking' => true,

        'access_levels' => [
            'admin' => [
                'name' => 'Administrator',
                'permissions' => ['view_all', 'create', 'edit', 'delete', 'export'],
            ],
            'manager' => [
                'name' => 'Manager',
                'permissions' => ['view_department', 'create', 'edit', 'export'],
            ],
            'analyst' => [
                'name' => 'Analyst',
                'permissions' => ['view_assigned', 'create', 'export'],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'permissions' => ['view_assigned'],
            ],
        ],

        'data_classification' => [
            'public' => 'Public',
            'internal' => 'Internal',
            'confidential' => 'Confidential',
            'restricted' => 'Restricted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    | External system integration configuration
    */
    'integrations' => [
        'api_enabled' => true,
        'webhook_support' => true,
        'external_data_sources' => true,
        'third_party_tools' => false,

        'supported_apis' => [
            'rest' => [
                'enabled' => true,
                'authentication' => ['api_key', 'bearer_token'],
            ],
            'graphql' => [
                'enabled' => false,
                'authentication' => ['bearer_token'],
            ],
        ],

        'export_destinations' => [
            's3' => [
                'enabled' => false,
                'bucket' => env('ANALYTICS_S3_BUCKET'),
            ],
            'ftp' => [
                'enabled' => false,
                'host' => env('ANALYTICS_FTP_HOST'),
            ],
            'email' => [
                'enabled' => true,
                'max_attachment_size' => '25MB',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    | Scheduled analytics tasks and reports
    */
    'scheduling' => [
        'enabled' => true,
        'cron_enabled' => true,
        'queue_scheduling' => true,
        'max_concurrent_jobs' => 5,
        'job_timeout' => 3600,

        'predefined_schedules' => [
            'hourly' => '0 * * * *',
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            'monthly' => '0 0 1 * *',
            'quarterly' => '0 0 1 */3 *',
            'yearly' => '0 0 1 1 *',
        ],

        'report_schedules' => [
            'executive_dashboard' => [
                'name' => 'Executive Dashboard',
                'schedule' => 'daily',
                'recipients' => ['executives'],
                'format' => 'pdf',
            ],
            'operational_summary' => [
                'name' => 'Operational Summary',
                'schedule' => 'weekly',
                'recipients' => ['operations'],
                'format' => 'excel',
            ],
            'security_metrics' => [
                'name' => 'Security Metrics',
                'schedule' => 'monthly',
                'recipients' => ['security'],
                'format' => 'pdf',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visualization Configuration
    |--------------------------------------------------------------------------
    | Chart and visualization settings
    */
    'visualization' => [
        'enabled' => true,
        'interactive_charts' => true,
        'responsive_charts' => true,
        'export_charts' => true,
        'animation_enabled' => true,

        'color_schemes' => [
            'default' => ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'],
            'monochrome' => ['#374151', '#6b7280', '#9ca3af', '#d1d5db', '#f3f4f6'],
            'rainbow' => ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6'],
        ],

        'chart_defaults' => [
            'responsive' => true,
            'maintain_aspect_ratio' => false,
            'plugins' => [
                'legend' => ['display' => true],
                'tooltip' => ['enabled' => true],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    | Default values for analytics components
    */
    'defaults' => [
        'date_range' => '30 days',
        'chart_type' => 'line',
        'aggregation_function' => 'avg',
        'refresh_interval' => 300, // seconds
        'export_format' => 'pdf',
        'page_size' => 25,
        'cache_duration' => 3600, // seconds
        'decimal_places' => 2,
        'currency' => 'USD',
        'timezone' => 'UTC',
        'locale' => 'en_US',
    ],
];
