<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NetServa CLI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for NetServa CLI package. These settings control
    | path resolution, SSH behavior, and command defaults.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Directory Paths
    |--------------------------------------------------------------------------
    |
    | NetServa base directory - all other paths are relative to this.
    |
    */
    'paths' => [
        'ns' => env('NS', env('HOME').'/.ns'),
        // SSH uses standard ~/.ssh/ directory
        'ssh_config_dir' => env('HOME').'/.ssh/hosts',
        'ssh_keys_dir' => env('HOME').'/.ssh/keys',
        'ssh_mux_dir' => env('HOME').'/.ssh/mux',
    ],

    /*
    |--------------------------------------------------------------------------
    | SSH Configuration
    |--------------------------------------------------------------------------
    |
    | SSH-related settings for remote execution and key management.
    |
    */
    'ssh' => [
        'timeout' => 30,
        'port' => 22,
        'default_user' => 'root',
        'key_types' => ['rsa', 'ed25519'],
        'default_key_size' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Execution
    |--------------------------------------------------------------------------
    |
    | Settings for executing commands on remote servers.
    |
    */
    'remote' => [
        'sync_shell_env' => true,
        'shell_env_path' => '~/.rc',  // NS 3.0 uses ~/.rc (not legacy ~/.sh)
        'source_shrc' => true,
        'require_root' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | VHost Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for managing virtual host configurations.
    |
    */
    'vhost' => [
        'env_file_variables' => 53,  // Expected number of variables
        'backup_configs' => true,
        'validate_structure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Defaults
    |--------------------------------------------------------------------------
    |
    | Default behavior for CLI commands.
    |
    */
    'commands' => [
        'show_progress' => true,
        'confirm_destructive' => true,
        'verbose_output' => false,
        'dry_run' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | CLI-specific logging configuration.
    |
    */
    'logging' => [
        'enabled' => true,
        'level' => env('NETSERVA_CLI_LOG_LEVEL', 'info'),
        'file' => env('NS', env('HOME').'/.ns').'/storage/logs/cli.log',
    ],
];
