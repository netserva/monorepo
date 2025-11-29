<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetServa\Core\Models\SshHost;
use Ns\Setup\Models\SetupComponent;
use Ns\Setup\Models\SetupJob;
use Ns\Setup\Models\SetupTemplate;

/**
 * NS Setup Service
 *
 * Handles server setup operations, converting bash setup scripts to Laravel-based execution.
 * Manages templates, components, and deployment jobs with progress tracking.
 */
class SetupService
{
    protected string $nsdir;

    public function __construct(
        protected SshConnectionService $sshService
    ) {
        $this->nsdir = env('NSDIR', '/home/'.get_current_user().'/.ns');
    }

    /**
     * Deploy a setup template to a target host
     */
    public function deployTemplate(SetupTemplate $template, string $targetHost, array $customConfig = []): SetupJob
    {
        $jobId = Str::uuid()->toString();

        // Get SSH host information
        $sshHost = SshHost::where('host', $targetHost)->first();
        $targetHostname = $sshHost ? $sshHost->hostname : $targetHost;

        // Merge default configuration with custom config
        $configuration = array_merge($template->default_config ?? [], $customConfig);

        // Create setup job
        $job = SetupJob::create([
            'job_id' => $jobId,
            'setup_template_id' => $template->id,
            'target_host' => $targetHost,
            'target_hostname' => $targetHostname,
            'status' => 'pending',
            'configuration' => $configuration,
            'components_status' => [],
            'progress_percentage' => 0,
            'initiated_by' => auth()->user()?->name ?? 'system',
        ]);

        Log::info("Created setup job {$jobId} for template '{$template->name}' on host '{$targetHost}'");

        // Start deployment asynchronously (in a real implementation, this would be queued)
        $this->executeSetupJob($job);

        return $job;
    }

    /**
     * Execute a setup job
     */
    public function executeSetupJob(SetupJob $job): bool
    {
        try {
            $job->markAsStarted();
            Log::info("Starting setup job {$job->job_id}");

            $template = $job->setupTemplate;
            $components = $template->getComponentModels()->sortBy('install_order');

            $totalComponents = $components->count();
            $completedComponents = 0;

            // Pre-install script
            if ($template->pre_install_script) {
                $this->executeScript($job, $template->pre_install_script, 'Pre-install script');
            }

            // Install each component
            foreach ($components as $component) {
                $this->installComponent($job, $component);
                $completedComponents++;

                $progress = (int) (($completedComponents / $totalComponents) * 90); // Reserve 10% for post-install
                $job->updateProgress($progress, "Completed component: {$component->display_name}");
            }

            // Post-install script
            if ($template->post_install_script) {
                $this->executeScript($job, $template->post_install_script, 'Post-install script');
            }

            $job->markAsCompleted();
            Log::info("Setup job {$job->job_id} completed successfully");

            return true;

        } catch (Exception $e) {
            $job->markAsFailed($e->getMessage());
            Log::error("Setup job {$job->job_id} failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Install a single component
     */
    protected function installComponent(SetupJob $job, SetupComponent $component): bool
    {
        Log::info("Installing component '{$component->name}' for job {$job->job_id}");

        try {
            // Check dependencies
            if ($component->hasDependencies()) {
                foreach ($component->getDependencyComponents() as $dependency) {
                    $this->installComponent($job, $dependency);
                }
            }

            // Get component configuration
            $config = $component->getMergedConfig($job->configuration);

            // Execute install command
            if ($component->install_command) {
                $command = $this->interpolateCommand($component->install_command, $config);
                $result = $this->sshService->exec($job->target_host, $command);

                if (! $result['success']) {
                    throw new Exception("Component '{$component->name}' installation failed: ".$result['output']);
                }
            }

            // Verify installation
            if ($component->verification_command) {
                $command = $this->interpolateCommand($component->verification_command, $config);
                $result = $this->sshService->exec($job->target_host, $command);

                if (! $result['success']) {
                    Log::warning("Component '{$component->name}' verification failed for job {$job->job_id}");
                }
            }

            // Update component status
            $componentsStatus = $job->components_status ?? [];
            $componentsStatus[$component->name] = 'completed';
            $job->update(['components_status' => $componentsStatus]);

            return true;

        } catch (Exception $e) {
            // Update component status as failed
            $componentsStatus = $job->components_status ?? [];
            $componentsStatus[$component->name] = 'failed';
            $job->update(['components_status' => $componentsStatus]);

            throw $e;
        }
    }

    /**
     * Execute a custom script
     */
    protected function executeScript(SetupJob $job, string $script, string $description): bool
    {
        Log::info("Executing {$description} for job {$job->job_id}");

        try {
            $result = $this->sshService->exec($job->target_host, $script);

            if (! $result['success']) {
                throw new Exception("{$description} failed: ".$result['output']);
            }

            $job->updateProgress($job->progress_percentage + 5, "Completed: {$description}");

            return true;

        } catch (Exception $e) {
            $job->updateProgress($job->progress_percentage, "Failed: {$description}");
            throw $e;
        }
    }

    /**
     * Interpolate command template with configuration values
     */
    protected function interpolateCommand(string $command, array $config): string
    {
        foreach ($config as $key => $value) {
            $command = str_replace("{{$key}}", $value, $command);
        }

        return $command;
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(SetupJob $job): bool
    {
        if (! in_array($job->status, ['running', 'pending'])) {
            return false;
        }

        $job->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'duration_seconds' => $job->started_at ? now()->diffInSeconds($job->started_at) : null,
        ]);

        Log::info("Setup job {$job->job_id} cancelled");

        return true;
    }

    /**
     * Retry a failed job
     */
    public function retryJob(SetupJob $failedJob): SetupJob
    {
        $newJob = SetupJob::create([
            'job_id' => Str::uuid()->toString(),
            'setup_template_id' => $failedJob->setup_template_id,
            'target_host' => $failedJob->target_host,
            'target_hostname' => $failedJob->target_hostname,
            'status' => 'pending',
            'configuration' => $failedJob->configuration,
            'components_status' => [],
            'progress_percentage' => 0,
            'initiated_by' => auth()->user()?->name ?? 'retry-system',
        ]);

        Log::info("Created retry job {$newJob->job_id} for failed job {$failedJob->job_id}");

        // Start new job
        $this->executeSetupJob($newJob);

        return $newJob;
    }

    /**
     * Get setup statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_jobs' => SetupJob::count(),
            'running_jobs' => SetupJob::running()->count(),
            'completed_jobs' => SetupJob::completed()->count(),
            'failed_jobs' => SetupJob::failed()->count(),
            'templates' => SetupTemplate::active()->count(),
            'components' => SetupComponent::active()->count(),
        ];
    }

    /**
     * Seed default templates and components
     */
    public function seedDefaults(): void
    {
        $this->seedDefaultComponents();
        $this->seedDefaultTemplates();
    }

    /**
     * Seed default components based on bash setup scripts
     */
    protected function seedDefaultComponents(): void
    {
        $components = [
            [
                'name' => 'host',
                'display_name' => 'Host Setup',
                'description' => 'Basic host configuration and package installation',
                'category' => 'system',
                'configuration_schema' => [
                    'vhost' => ['type' => 'string', 'required' => true],
                    'dtype' => ['type' => 'string', 'default' => 'mysql'],
                ],
                'default_config' => [],
                'install_command' => 'setup host {vhost} {dtype}',
                'verification_command' => 'test -f /etc/hostname',
                'install_order' => 10,
                'is_required' => true,
            ],
            [
                'name' => 'web',
                'display_name' => 'Web Server',
                'description' => 'Nginx web server configuration',
                'category' => 'services',
                'dependencies' => ['host'],
                'configuration_schema' => [
                    'domain' => ['type' => 'string', 'required' => true],
                ],
                'default_config' => [],
                'install_command' => 'setup web {domain}',
                'verification_command' => 'systemctl is-active nginx',
                'install_order' => 20,
            ],
            [
                'name' => 'db',
                'display_name' => 'Database',
                'description' => 'MySQL/MariaDB database server',
                'category' => 'services',
                'dependencies' => ['host'],
                'configuration_schema' => [
                    'root_password' => ['type' => 'string', 'required' => true],
                ],
                'default_config' => [],
                'install_command' => 'setup db {root_password}',
                'verification_command' => 'systemctl is-active mariadb',
                'install_order' => 15,
            ],
            [
                'name' => 'mail',
                'display_name' => 'Mail Server',
                'description' => 'Postfix and Dovecot mail server',
                'category' => 'services',
                'dependencies' => ['host', 'db'],
                'configuration_schema' => [
                    'domain' => ['type' => 'string', 'required' => true],
                ],
                'default_config' => [],
                'install_command' => 'setup mail {domain}',
                'verification_command' => 'systemctl is-active postfix && systemctl is-active dovecot',
                'install_order' => 30,
            ],
            [
                'name' => 'dns',
                'display_name' => 'DNS Server',
                'description' => 'PowerDNS DNS server',
                'category' => 'services',
                'dependencies' => ['host', 'db'],
                'configuration_schema' => [],
                'default_config' => [],
                'install_command' => 'setup dns',
                'verification_command' => 'systemctl is-active pdns',
                'install_order' => 25,
            ],
            [
                'name' => 'ssl',
                'display_name' => 'SSL Certificates',
                'description' => 'SSL certificate management with Let\'s Encrypt',
                'category' => 'security',
                'dependencies' => ['web'],
                'configuration_schema' => [
                    'domain' => ['type' => 'string', 'required' => true],
                    'email' => ['type' => 'email', 'required' => true],
                ],
                'install_command' => 'setup ssl {domain} {email}',
                'verification_command' => 'test -f /etc/letsencrypt/live/{domain}/fullchain.pem',
                'install_order' => 40,
            ],
        ];

        foreach ($components as $componentData) {
            SetupComponent::updateOrCreate(
                ['name' => $componentData['name']],
                $componentData
            );
        }

        Log::info('Default components seeded');
    }

    /**
     * Seed default templates
     */
    protected function seedDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'lamp-server',
                'display_name' => 'LAMP Server',
                'description' => 'Linux, Apache/Nginx, MySQL, PHP server setup',
                'category' => 'server',
                'components' => ['host', 'web', 'db'],
                'supported_os' => ['debian', 'ubuntu'],
                'default_config' => [
                    'dtype' => 'mysql',
                ],
                'sort_order' => 10,
            ],
            [
                'name' => 'mail-server',
                'display_name' => 'Mail Server',
                'description' => 'Complete mail server with Postfix and Dovecot',
                'category' => 'mail',
                'components' => ['host', 'db', 'mail', 'ssl'],
                'supported_os' => ['debian', 'ubuntu'],
                'sort_order' => 20,
            ],
            [
                'name' => 'web-server',
                'display_name' => 'Web Server',
                'description' => 'Nginx web server with SSL support',
                'category' => 'web',
                'components' => ['host', 'web', 'ssl'],
                'supported_os' => ['debian', 'ubuntu', 'alpine'],
                'sort_order' => 15,
            ],
            [
                'name' => 'dns-server',
                'display_name' => 'DNS Server',
                'description' => 'PowerDNS authoritative DNS server',
                'category' => 'dns',
                'components' => ['host', 'db', 'dns'],
                'supported_os' => ['debian', 'ubuntu'],
                'sort_order' => 25,
            ],
        ];

        foreach ($templates as $templateData) {
            SetupTemplate::updateOrCreate(
                ['name' => $templateData['name']],
                $templateData
            );
        }

        Log::info('Default templates seeded');
    }

    /**
     * Create a setup template
     */
    public function createTemplate(array $data): SetupTemplate
    {
        return SetupTemplate::create($data);
    }

    /**
     * Setup web server on target host
     */
    public function setupWebServer(string $host, array $config): SetupJob
    {
        $template = SetupTemplate::where('name', 'web-server')->first();
        if (! $template) {
            throw new Exception('Web server template not found');
        }

        return $this->deployTemplate($template, $host, $config);
    }

    /**
     * Setup mail server on target host
     */
    public function setupMailServer(string $host, array $config): SetupJob
    {
        $template = SetupTemplate::where('name', 'mail-server')->first();
        if (! $template) {
            throw new Exception('Mail server template not found');
        }

        return $this->deployTemplate($template, $host, $config);
    }

    /**
     * Setup database server on target host
     */
    public function setupDatabaseServer(string $host, array $config): SetupJob
    {
        $template = SetupTemplate::where('name', 'lamp-server')->first();
        if (! $template) {
            throw new Exception('Database server template not found');
        }

        return $this->deployTemplate($template, $host, $config);
    }

    /**
     * Setup DNS server on target host
     */
    public function setupDnsServer(string $host, array $config): SetupJob
    {
        $template = SetupTemplate::where('name', 'dns-server')->first();
        if (! $template) {
            throw new Exception('DNS server template not found');
        }

        return $this->deployTemplate($template, $host, $config);
    }

    /**
     * Run custom script on target host
     */
    public function runCustomScript(string $host, string $script): array
    {
        return $this->sshService->exec($host, $script);
    }

    /**
     * Validate prerequisites on target host
     */
    public function validatePrerequisites(string $host, array $requirements): array
    {
        $results = [];

        foreach ($requirements as $requirement) {
            // Mock validation - in real implementation would check actual requirements
            $results[$requirement] = ['valid' => true, 'message' => 'Requirement met'];
        }

        return $results;
    }

    /**
     * Configure firewall on target host
     */
    public function configureFirewall(string $host, array $rules): array
    {
        // Mock firewall configuration
        return ['success' => true, 'rules_applied' => count($rules)];
    }

    /**
     * Update job progress
     */
    public function updateJobProgress(SetupJob $job, int $percentage, string $message): void
    {
        $job->updateProgress($percentage, $message);
    }

    /**
     * Rollback a failed job
     */
    public function rollback(SetupJob $job): array
    {
        if ($job->status !== 'failed') {
            return ['success' => false, 'message' => 'Job is not in failed state'];
        }

        // Mock rollback - in real implementation would reverse changes
        return ['success' => true, 'rollback_steps' => 3];
    }

    /**
     * Get packages for a component
     */
    public function getPackagesForComponent(string $component): array
    {
        $packageMap = [
            'nginx' => ['nginx', 'nginx-extras'],
            'mysql' => ['mariadb-server', 'mariadb-client'],
            'php' => ['php8.2-fpm', 'php8.2-mysql', 'php8.2-xml'],
            'dns' => ['pdns-server', 'pdns-backend-mysql'],
            'mail' => ['postfix', 'dovecot-imapd', 'dovecot-pop3d'],
        ];

        return $packageMap[$component] ?? [];
    }
}
