<?php

namespace NetServa\Ops\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Ops\Services\AlertService;

class CheckAlertsCommand extends Command
{
    protected $signature = 'analytics:check-alerts';

    protected $description = 'Check all active alerts and send notifications';

    public function handle(AlertService $alertService): int
    {
        $this->info('Checking alerts...');

        $results = $alertService->checkAll();

        $this->info("Checked {$results['total']} alerts");

        if ($results['triggered'] > 0) {
            $this->warn("Triggered {$results['triggered']} alerts");
        } else {
            $this->info('No alerts triggered');
        }

        return self::SUCCESS;
    }
}
