<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\SystemServiceManager;

/**
 * ServiceCommand - Manage system services
 */
class ServiceCommand extends Command
{
    protected $signature = 'ns:service 
                           {action : start|stop|restart|status|list}
                           {service? : Service name}
                           {--all : Apply to all services}';

    protected $description = 'Manage system services';

    public function handle(SystemServiceManager $serviceManager): int
    {
        $action = $this->argument('action');
        $service = $this->argument('service');
        $all = $this->option('all');

        try {
            match ($action) {
                'list' => $this->listServices($serviceManager),
                'status' => $this->showStatus($serviceManager, $service, $all),
                'start' => $this->startService($serviceManager, $service, $all),
                'stop' => $this->stopService($serviceManager, $service, $all),
                'restart' => $this->restartService($serviceManager, $service, $all),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function listServices(SystemServiceManager $serviceManager): void
    {
        $services = $serviceManager->getAllServices();

        $this->table(
            ['Service', 'Status', 'Description'],
            $services->map(fn ($service) => [
                $service->name,
                $service->status,
                $service->description ?? '-',
            ])
        );
    }

    private function showStatus(SystemServiceManager $serviceManager, ?string $service, bool $all): void
    {
        if ($all) {
            $this->listServices($serviceManager);

            return;
        }

        if (! $service) {
            $this->error('Service name required when not using --all');

            return;
        }

        $status = $serviceManager->getServiceStatus($service);
        $this->info("Service {$service}: {$status}");
    }

    private function startService(SystemServiceManager $serviceManager, ?string $service, bool $all): void
    {
        if ($all) {
            $result = $serviceManager->startAllServices();
            $this->info("Started {$result} services");

            return;
        }

        if (! $service) {
            $this->error('Service name required when not using --all');

            return;
        }

        $serviceManager->startService($service);
        $this->info("Started service: {$service}");
    }

    private function stopService(SystemServiceManager $serviceManager, ?string $service, bool $all): void
    {
        if ($all) {
            $result = $serviceManager->stopAllServices();
            $this->info("Stopped {$result} services");

            return;
        }

        if (! $service) {
            $this->error('Service name required when not using --all');

            return;
        }

        $serviceManager->stopService($service);
        $this->info("Stopped service: {$service}");
    }

    private function restartService(SystemServiceManager $serviceManager, ?string $service, bool $all): void
    {
        if ($all) {
            $result = $serviceManager->restartAllServices();
            $this->info("Restarted {$result} services");

            return;
        }

        if (! $service) {
            $this->error('Service name required when not using --all');

            return;
        }

        $serviceManager->restartService($service);
        $this->info("Restarted service: {$service}");
    }
}
