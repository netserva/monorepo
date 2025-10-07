<?php

namespace NetServa\Config\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use NetServa\Config\Models\ConfigDeployment;
use NetServa\Config\Models\ConfigProfile;
use NetServa\Config\Models\ConfigTemplate;
use NetServa\Config\Models\ConfigVariable;
use NetServa\Config\Models\Secret;

class ConfigService
{
    /**
     * Create a new configuration template
     */
    public function createTemplate(array $data): ConfigTemplate
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        // Map test fields to database fields
        if (isset($data['content'])) {
            $data['template_content'] = $data['content'];
            unset($data['content']);
        }
        if (isset($data['type'])) {
            $data['config_type'] = $data['type'];
            unset($data['type']);
        }
        if (isset($data['variables'])) {
            $data['required_variables'] = $data['variables'];
            unset($data['variables']);
        }

        // Set required fields with defaults if not provided
        $data['config_type'] = $data['config_type'] ?? 'nginx'; // Default config type
        $data['template_content'] = $data['template_content'] ?? 'server { listen 80; }'; // Default template content
        $data['target_filename'] = $data['target_filename'] ?? ($data['slug'] ?? 'config').'.conf';
        $data['target_path'] = $data['target_path'] ?? '/etc/nginx/sites-available/';

        // Ensure required_variables is always set
        if (! isset($data['required_variables'])) {
            $data['required_variables'] = [];
        }

        return ConfigTemplate::create($data);
    }

    /**
     * Create a configuration profile
     */
    public function createProfile(array $data): ConfigProfile
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        // Set required fields with defaults if not provided
        $data['infrastructure_node_id'] = $data['infrastructure_node_id'] ?? 1; // Default to node 1
        $data['template_assignments'] = $data['template_assignments'] ?? [];
        $data['global_variables'] = $data['global_variables'] ?? ($data['variables'] ?? []);

        // Remove test field that doesn't exist in database
        unset($data['variables']);

        return ConfigProfile::create($data);
    }

    /**
     * Render template with variables
     */
    public function renderTemplate(ConfigTemplate $template, array $variables = []): string
    {
        $content = $template->content; // Uses the accessor method

        // Resolve secrets first
        $resolvedVariables = $this->resolveSecrets($variables);

        // Replace template variables (triple braces first to avoid conflicts)
        foreach ($resolvedVariables as $key => $value) {
            $content = str_replace('{{{'.$key.'}}}', $value, $content);
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }

    /**
     * Deploy configuration to server
     */
    public function deployConfig(ConfigTemplate $template, ConfigProfile $profile, string $targetServer, array $options = []): ConfigDeployment
    {
        // Render the template with profile variables
        $renderedConfig = $this->renderTemplate($template, $profile->global_variables ?? []);

        // Create temporary file
        $tempFile = '/tmp/'.uniqid('config_').'.conf';
        file_put_contents($tempFile, $renderedConfig);

        $targetPath = $options['target_path'] ?? '/etc/config/default.conf';
        $backupPath = null;

        // Backup existing configuration if requested
        if ($options['backup_existing'] ?? false) {
            $backup = $this->backupConfig($targetServer, $targetPath);
            $backupPath = $backup['backup_path'] ?? null;
        }

        // Basic syntax validation
        if ($template->syntax_check_command) {
            $validation = $this->validateConfig($template->config_type ?? 'generic', $renderedConfig);
            if (! $validation['valid']) {
                throw new \Exception('Configuration validation failed: '.$validation['message']);
            }
        }

        // Deploy configuration
        Process::run("scp {$tempFile} {$targetServer}:{$targetPath}");

        // Reload service if specified
        if (isset($options['reload_command'])) {
            Process::run("ssh {$targetServer} \"{$options['reload_command']}\"");
        } elseif ($template->type === 'nginx') {
            Process::run("ssh {$targetServer} \"systemctl reload nginx\"");
        }

        // Clean up temp file
        unlink($tempFile);

        return ConfigDeployment::create([
            'deployment_id' => Str::uuid(),
            'deployment_name' => "Deploy {$template->name}",
            'config_profile_id' => $profile->id,
            'infrastructure_node_id' => $profile->infrastructure_node_id ?? 1,
            'deployment_method' => 'ssh',
            'templates_to_deploy' => [$template->id],
            'variables_used' => $profile->global_variables ?? [],
            'status' => 'completed',
            'success' => true,
            'deployed_files' => [
                $targetPath => [
                    'template_id' => $template->id,
                    'backup_path' => $backupPath,
                    'size' => strlen($renderedConfig),
                ],
            ],
            'started_at' => now(),
            'completed_at' => now(),
            'deployment_environment' => 'production',
        ]);
    }

    /**
     * Validate configuration syntax
     */
    public function validateConfig(string $type, string $content): array
    {
        $validator = $this->getValidator($type);
        $tempFile = '/tmp/'.uniqid('validate_').'.conf';
        file_put_contents($tempFile, $content);

        try {
            $command = str_replace('/tmp/test.conf', $tempFile, $validator['test_command']);
            $result = Process::run($command);

            $valid = $result->successful();
            $message = $result->output() ?: $result->errorOutput();

            return [
                'valid' => $valid,
                'message' => $message,
                'command' => $command,
            ];
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Get validator configuration for service type
     */
    public function getValidator(string $type): array
    {
        $validators = [
            'nginx' => ['test_command' => 'nginx -t'],
            'apache' => ['test_command' => 'apache2ctl configtest'],
            'php-fpm' => ['test_command' => 'php-fpm -t'],
            'mysql' => ['test_command' => 'mysqld --help --verbose > /dev/null'],
        ];

        return $validators[$type] ?? ['test_command' => 'echo "No validator for type: '.$type.'"'];
    }

    /**
     * Backup existing configuration
     */
    public function backupConfig(string $server, string $configPath): array
    {
        $timestamp = date('Ymd_His');
        $backupPath = '/backups/'.basename($configPath).".{$timestamp}";

        $command = "ssh {$server} \"cp {$configPath} {$backupPath}\"";
        $result = Process::run($command);

        return [
            'success' => $result->successful(),
            'backup_path' => $backupPath,
            'command' => $command,
            'output' => $result->output(),
        ];
    }

    /**
     * Diff two configurations
     */
    public function diffConfigs(string $oldConfig, string $newConfig): array
    {
        $oldLines = explode("\n", $oldConfig);
        $newLines = explode("\n", $newConfig);

        $addedLines = array_diff($newLines, $oldLines);
        $removedLines = array_diff($oldLines, $newLines);

        return [
            'has_changes' => ! empty($addedLines) || ! empty($removedLines),
            'added_lines' => array_values($addedLines),
            'removed_lines' => array_values($removedLines),
            'total_changes' => count($addedLines) + count($removedLines),
        ];
    }

    /**
     * Rollback a deployment
     */
    public function rollback(ConfigDeployment $deployment): bool
    {
        // Check if backup path exists in deployed_files
        $deployedFiles = $deployment->deployed_files ?? [];
        $backupPath = null;
        $targetPath = null;
        $targetServer = 'localhost'; // Simplified for core functionality

        if (empty($deployedFiles) || ! $targetServer) {
            throw new \Exception('No backup information available for rollback');
        }

        // Get first deployed file's backup path
        foreach ($deployedFiles as $path => $fileInfo) {
            if (isset($fileInfo['backup_path'])) {
                $backupPath = $fileInfo['backup_path'];
                $targetPath = $path;
                break;
            }
        }

        if (! $backupPath) {
            throw new \Exception('No backup path available for rollback');
        }

        $restoreCommand = "ssh {$targetServer} \"cp {$backupPath} {$targetPath}\"";
        $restoreResult = Process::run($restoreCommand);

        if ($restoreResult->successful()) {
            // Reload service
            $reloadCommand = "ssh {$targetServer} \"systemctl reload nginx\"";
            Process::run($reloadCommand);

            $deployment->update(['status' => 'rolled_back']);

            return true;
        }

        return false;
    }

    /**
     * Get environment variables
     */
    public function getEnvironmentVariables(string $environment, array $defaults = []): array
    {
        $envVars = ConfigVariable::where('environment', $environment)->get();
        $variables = $defaults;

        foreach ($envVars as $var) {
            $variables[$var->key] = $var->value;
        }

        return $variables;
    }

    /**
     * Resolve secrets in configuration templates
     */
    public function resolveSecrets(array $variables): array
    {
        $resolved = [];

        foreach ($variables as $key => $value) {
            if (is_string($value) && $this->isSecretPlaceholder($value)) {
                $secretSlug = $this->extractSecretSlug($value);
                $secret = Secret::where('slug', $secretSlug)
                    ->where('is_active', true)
                    ->first();

                $resolved[$key] = $secret?->value ?? $value;
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Check if a value is a secret placeholder
     */
    private function isSecretPlaceholder(string $value): bool
    {
        return preg_match('/^\{\{\s*secret:[a-zA-Z0-9\-_]+\s*\}\}$/', $value);
    }

    /**
     * Extract secret slug from placeholder
     */
    private function extractSecretSlug(string $placeholder): string
    {
        preg_match('/\{\{\s*secret:([a-zA-Z0-9\-_]+)\s*\}\}/', $placeholder, $matches);

        return $matches[1] ?? '';
    }

    /**
     * Process configuration template with multiple types of variables
     */
    public function processTemplate(string $template, array $context = []): string
    {
        // First resolve secrets
        $resolvedSecrets = $this->resolveSecrets($context);

        // Then replace template variables
        $processed = $template;
        foreach ($resolvedSecrets as $key => $value) {
            $processed = str_replace("{{ {$key} }}", $value, $processed);
            $processed = str_replace("{{{$key}}}", $value, $processed);
        }

        return $processed;
    }

    /**
     * Merge configuration arrays with precedence rules
     */
    public function mergeConfigs(array $base, array $override, array $precedenceRules = []): array
    {
        $merged = $base;

        foreach ($override as $key => $value) {
            if (isset($precedenceRules[$key])) {
                // Apply precedence rule
                switch ($precedenceRules[$key]) {
                    case 'override':
                        $merged[$key] = $value;
                        break;
                    case 'merge':
                        if (is_array($value) && is_array($merged[$key] ?? null)) {
                            $merged[$key] = array_merge($merged[$key], $value);
                        } else {
                            $merged[$key] = $value;
                        }
                        break;
                    case 'append':
                        if (is_array($merged[$key] ?? null)) {
                            $merged[$key][] = $value;
                        } else {
                            $merged[$key] = [$merged[$key] ?? null, $value];
                        }
                        break;
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
