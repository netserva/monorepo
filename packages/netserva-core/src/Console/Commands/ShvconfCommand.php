<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Fleet\Models\FleetVhost;
use Symfony\Component\Console\Helper\Table;

/**
 * Show VHost Configuration Command
 *
 * Follows NetServa CRUD pattern: shvconf (show vhost config)
 * Usage: shvconf <vnode> <vhost> [variable]
 * Example: shvconf markc markc.goldcoast.org WPATH
 *
 * DATABASE-FIRST: Displays vconfs from dedicated table
 * DEFAULT OUTPUT: Plain sorted bash-sourceable format (VAR='value')
 */
class ShvconfCommand extends BaseNetServaCommand
{
    protected $signature = 'shvconf {vnode : VNode name}
                           {vhost : VHost domain}
                           {variable? : Specific variable to show}
                           {--table : Output in formatted table with groups}
                           {--json : Output in JSON format}
                           {--all : Show all variables including empty ones}
                           {--dry-run : Check if vhost exists and show what would be displayed}';

    protected $description = 'Show VHost configuration variables (plain sorted bash-sourceable format)';

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');
            $variable = $this->argument('variable');

            // Check if VNode exists
            $vnode = \NetServa\Fleet\Models\FleetVnode::where('name', $VNODE)->first();

            if (! $vnode) {
                $this->error("❌ VNode '{$VNODE}' not found in database");
                $this->line("   💡 Run: php artisan addfleet {$VNODE}");

                return 1;
            }

            // Find VHost in database
            $vhost = FleetVhost::where('domain', $VHOST)
                ->where('vnode_id', $vnode->id)
                ->first();

            if (! $vhost) {
                if ($this->option('dry-run')) {
                    $this->info("🔍 DRY RUN: VHost '{$VHOST}' does not exist on vnode '{$VNODE}'");
                    $this->line('');
                    $this->line("   VNode exists: ✅ {$VNODE}");
                    $this->line("   VHost exists: ❌ {$VHOST}");
                    $this->line('');
                    $this->line('   💡 Next steps:');
                    $this->line("      1. Preview variables: addvconf {$VNODE} {$VHOST} --dry-run");
                    $this->line("      2. Create vhost:      addvhost {$VNODE} {$VHOST}");

                    return 0;
                }

                $this->error("❌ VHost '{$VHOST}' not found on vnode '{$VNODE}'");
                $this->line("   💡 Run: addvhost {$VNODE} {$VHOST}");

                return 1;
            }

            // Get variables from vconfs table (preferred) with JSON fallback
            $envVars = $vhost->getAllEnvVars();

            if (empty($envVars)) {
                if ($this->option('dry-run')) {
                    $this->info('🔍 DRY RUN: VHost exists but has no configuration variables');
                    $this->line('');
                    $this->line("   VHost exists: ✅ {$VHOST}");
                    $this->line('   Variables:    ❌ 0 configured');
                    $this->line('');
                    $this->line("   💡 Initialize configuration: addvconf {$VNODE} {$VHOST}");

                    return 0;
                }

                $this->warn("⚠️  No configuration variables found for {$VHOST}");
                $this->line("   💡 Run: addvconf {$VNODE} {$VHOST}");

                return 1;
            }

            // Dry-run mode - show what would be displayed
            if ($this->option('dry-run')) {
                $count = count($envVars);
                $this->info("🔍 DRY RUN: Would display {$count} variables for {$VHOST}");
                $this->line('');
                $this->line("   VHost exists: ✅ {$VHOST}");
                $this->line("   Variables:    ✅ {$count} configured");
                $this->line('');
                $this->line("   💡 View variables: shvconf {$VNODE} {$VHOST}");
                $this->line('   💡 Format options: --table, --json');

                return 0;
            }

            // Show specific variable
            if ($variable) {
                return $this->showSpecificVariable($envVars, $variable, $VHOST);
            }

            // JSON output
            if ($this->option('json')) {
                $this->line(json_encode($envVars, JSON_PRETTY_PRINT));

                return 0;
            }

            // Table format (grouped and formatted)
            if ($this->option('table')) {
                return $this->showTableFormat($envVars, $VHOST, $VNODE);
            }

            // Default: plain sorted bash-sourceable format
            return $this->showPlainFormat($envVars);
        });
    }

    protected function showSpecificVariable(array $envVars, string $variable, string $vhost): int
    {
        $variable = strtoupper($variable);

        if (! isset($envVars[$variable])) {
            $this->error("❌ Variable {$variable} not found for {$vhost}");

            return 1;
        }

        $this->line("{$envVars[$variable]}");

        return 0;
    }

    /**
     * Show plain sorted bash-sourceable format (DEFAULT)
     *
     * Output format: KEY='value'
     * Sorted alphabetically
     * Can be directly sourced: source <(shvconf vnode vhost)
     */
    protected function showPlainFormat(array $envVars): int
    {
        // Sort variables alphabetically
        ksort($envVars);

        // Output in plain VAR='value' format
        foreach ($envVars as $key => $value) {
            $escapedValue = addslashes($value);
            $this->line("{$key}='{$escapedValue}'");
        }

        return 0;
    }

    protected function showTableFormat(array $envVars, string $vhost, string $vnode): int
    {
        $this->line("<fg=blue>📋 VHost Configuration:</> <fg=yellow>{$vhost}</> <fg=gray>on</> <fg=cyan>{$vnode}</>");
        $this->line('');

        // Filter empty values unless --all specified
        if (! $this->option('all')) {
            $envVars = array_filter($envVars, fn ($value) => ! empty($value));
        }

        if (empty($envVars)) {
            $this->line('   <fg=gray>No environment variables configured</fg>');
            $this->line('   💡 Use: addvconf <vnode> <vhost> to add variables');

            return 0;
        }

        // Group variables by category
        $grouped = $this->groupVariables($envVars);

        foreach ($grouped as $category => $vars) {
            if (empty($vars)) {
                continue;
            }

            $this->line("<fg=blue>{$category}:</>");
            $table = new Table($this->output);
            $table->setHeaders(['Variable', 'Value']);

            foreach ($vars as $key => $value) {
                // Mask sensitive values
                if (str_contains(strtolower($key), 'pass')) {
                    $value = str_repeat('*', min(strlen($value), 16));
                }
                $table->addRow([$key, $value]);
            }

            $table->render();
            $this->line('');
        }

        $this->line('<fg=gray>Total variables: '.count($envVars).'</>');

        return 0;
    }

    protected function groupVariables(array $envVars): array
    {
        $grouped = [
            'Paths' => [],
            'User & Group' => [],
            'Database' => [],
            'Mail' => [],
            'Web Server' => [],
            'SSL/TLS' => [],
            'Other' => [],
        ];

        foreach ($envVars as $key => $value) {
            $upper = strtoupper($key);

            if (str_ends_with($upper, 'PATH')) {
                $grouped['Paths'][$key] = $value;
            } elseif (preg_match('/^(UUSER|U_UID|U_GID|WUGID)/', $upper)) {
                $grouped['User & Group'][$key] = $value;
            } elseif (preg_match('/^D(NAME|USER|PASS|TYPE|HOST|PORT)/', $upper)) {
                $grouped['Database'][$key] = $value;
            } elseif (preg_match('/^M(USER|PASS|PATH)/', $upper)) {
                $grouped['Mail'][$key] = $value;
            } elseif (preg_match('/^(WUGID|WPATH|ADMIN)/', $upper)) {
                $grouped['Web Server'][$key] = $value;
            } elseif (preg_match('/^(SSL|TLS|LEPATH)/', $upper)) {
                $grouped['SSL/TLS'][$key] = $value;
            } else {
                $grouped['Other'][$key] = $value;
            }
        }

        return $grouped;
    }
}
