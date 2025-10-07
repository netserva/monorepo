<?php

namespace NetServa\Ops\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Ops\Services\MetricsService;

class CollectMetricsCommand extends Command
{
    protected $signature = 'analytics:collect-metrics';

    protected $description = 'Collect data for all active metrics';

    public function handle(MetricsService $metricsService): int
    {
        $this->info('Collecting metrics...');

        $results = $metricsService->collectAll();

        $this->info("Collected {$results['successful']} metrics successfully");

        if ($results['failed'] > 0) {
            $this->warn("Failed to collect {$results['failed']} metrics");
        }

        return self::SUCCESS;
    }
}
