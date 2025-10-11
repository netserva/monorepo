<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\FcrDnsValidationService;

/**
 * DNS FCrDNS Verification Command
 *
 * Validates Forward-Confirmed Reverse DNS (FCrDNS) for a given host.
 * This is a mandatory requirement for email server deliverability.
 *
 * Usage:
 *   php artisan dns:verify markc.goldcoast.org 192.168.1.100
 *   php artisan dns:verify markc.goldcoast.org 192.168.1.100 --wait
 *   php artisan dns:verify markc.goldcoast.org 192.168.1.100 --wait --max-wait=60
 */
class DnsVerifyCommand extends Command
{
    protected $signature = 'dns:verify
                            {fqdn : Fully qualified domain name}
                            {ip : IP address}
                            {--wait : Wait for DNS propagation}
                            {--max-wait=30 : Maximum seconds to wait for propagation}
                            {--json : Output as JSON}';

    protected $description = 'Verify FCrDNS (Forward-Confirmed Reverse DNS) for a host';

    protected FcrDnsValidationService $dnsValidation;

    public function __construct(FcrDnsValidationService $dnsValidation)
    {
        parent::__construct();
        $this->dnsValidation = $dnsValidation;
    }

    public function handle(): int
    {
        $fqdn = $this->argument('fqdn');
        $ip = $this->argument('ip');
        $wait = $this->option('wait');
        $maxWait = (int) $this->option('max-wait');
        $jsonOutput = $this->option('json');

        if (! $jsonOutput) {
            $this->info("Verifying FCrDNS for $fqdn → $ip");
            $this->newLine();
        }

        // Wait for DNS propagation if requested
        if ($wait) {
            if (! $jsonOutput) {
                $this->line("Waiting for DNS propagation (max {$maxWait}s)...");
            }

            $propagated = $this->dnsValidation->waitForPropagation($fqdn, $ip, $maxWait);

            if (! $propagated) {
                if ($jsonOutput) {
                    $this->outputJson([
                        'success' => false,
                        'message' => 'DNS propagation timeout',
                        'fqdn' => $fqdn,
                        'ip' => $ip,
                        'waited' => $maxWait,
                    ]);
                } else {
                    $this->error('❌ DNS propagation timeout');
                    $this->showDnsDebugInfo($fqdn, $ip);
                }

                return self::FAILURE;
            }

            if (! $jsonOutput) {
                $this->info('✅ DNS propagated successfully');
                $this->newLine();
            }
        }

        // Validate FCrDNS
        $result = $this->dnsValidation->validate($fqdn, $ip);

        // JSON output
        if ($jsonOutput) {
            $this->outputJson([
                'success' => $result->hasFcrDns,
                'fqdn' => $fqdn,
                'ip' => $ip,
                'forward_dns' => [
                    'passed' => $result->hasForwardDns,
                    'resolved_ip' => $result->forwardIp,
                ],
                'reverse_dns' => [
                    'passed' => $result->hasReverseDns,
                    'resolved_fqdn' => $result->reverseFqdn,
                ],
                'fcrdns' => [
                    'passed' => $result->hasFcrDns,
                    'email_capable' => $result->hasFcrDns,
                ],
                'errors' => $result->errors,
                'warnings' => $result->warnings,
            ]);

            return $result->hasFcrDns ? self::SUCCESS : self::FAILURE;
        }

        // Human-readable output
        $this->displayResult('Forward DNS (A)', $result->hasForwardDns, $result->forwardIp);
        $this->displayResult('Reverse DNS (PTR)', $result->hasReverseDns, $result->reverseFqdn);
        $this->displayResult('FCrDNS Match', $result->hasFcrDns);

        $this->newLine();

        // Show warnings if any
        if (! empty($result->warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($result->warnings as $warning) {
                $this->line("  • $warning");
            }
            $this->newLine();
        }

        // Final result
        if ($result->hasFcrDns) {
            $this->info('✅ FCrDNS PASS - Server is email-capable');
            $this->newLine();
            $this->line('This server can send email with proper deliverability.');
            $this->line('Email recipients will see proper reverse DNS verification.');

            return self::SUCCESS;
        } else {
            $this->error('❌ FCrDNS FAIL - Server cannot send email reliably');
            $this->newLine();

            if (! empty($result->errors)) {
                $this->error('Errors:');
                foreach ($result->errors as $error) {
                    $this->line("  • $error");
                }
                $this->newLine();
            }

            $this->warn('Impact:');
            $this->line('  • Emails sent from this server will likely be marked as spam');
            $this->line('  • Many mail servers will reject emails from this IP');
            $this->line('  • SSL certificate DNS validation may fail');

            $this->newLine();
            $this->showDnsDebugInfo($fqdn, $ip);

            return self::FAILURE;
        }
    }

    /**
     * Display single validation result
     *
     * @param  string  $label  Result label
     * @param  bool  $passed  Whether validation passed
     * @param  string|null  $value  Optional result value
     */
    protected function displayResult(string $label, bool $passed, ?string $value = null): void
    {
        $icon = $passed ? '✅' : '❌';
        $status = $passed ? 'PASS' : 'FAIL';

        if ($value) {
            $this->line("$icon $label: $status → $value");
        } else {
            $this->line("$icon $label: $status");
        }
    }

    /**
     * Show DNS debug information
     *
     * @param  string  $fqdn  Fully qualified domain name
     * @param  string  $ip  IP address
     */
    protected function showDnsDebugInfo(string $fqdn, string $ip): void
    {
        $this->warn('Debug Information:');

        $info = $this->dnsValidation->getDnsDebugInfo($fqdn, $ip);

        // Forward DNS
        $forwardCheck = $info['checks']['forward_dns'] ?? [];
        $this->line('Forward DNS (A):');
        $this->line('  Resolved: '.($forwardCheck['success'] ? 'Yes' : 'No'));
        if (isset($forwardCheck['resolved_ip'])) {
            $this->line("  IP: {$forwardCheck['resolved_ip']}");
            $this->line('  Match: '.($forwardCheck['matches_expected'] ? 'Yes' : 'No'));
        }
        if (isset($forwardCheck['error'])) {
            $this->line("  Error: {$forwardCheck['error']}");
        }

        $this->newLine();

        // Reverse DNS
        $reverseCheck = $info['checks']['reverse_dns'] ?? [];
        $this->line('Reverse DNS (PTR):');
        $this->line('  Resolved: '.($reverseCheck['success'] ? 'Yes' : 'No'));
        if (isset($reverseCheck['resolved_fqdn'])) {
            $this->line("  FQDN: {$reverseCheck['resolved_fqdn']}");
            $this->line('  Match: '.($reverseCheck['matches_expected'] ? 'Yes' : 'No'));
        }
        if (isset($reverseCheck['error'])) {
            $this->line("  Error: {$reverseCheck['error']}");
        }

        $this->newLine();

        // Suggestions
        $this->info('Next Steps:');
        if (! ($forwardCheck['success'] ?? false)) {
            $this->line("  1. Create A record: $fqdn → $ip");
        }
        if (! ($reverseCheck['success'] ?? false)) {
            $octets = explode('.', $ip);
            $ptrName = "{$octets[3]}.{$octets[2]}.{$octets[1]}.{$octets[0]}.in-addr.arpa";
            $this->line("  2. Create PTR record: $ptrName → $fqdn");
        }
        if (! ($info['fcrdns_valid'] ?? false)) {
            $this->line('  3. Wait for DNS propagation (can take up to 48 hours)');
            $this->line('  4. Re-run this command with --wait flag');
        }
    }

    /**
     * Output JSON result
     *
     * @param  array  $data  Data to output
     */
    protected function outputJson(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
