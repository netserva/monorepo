<?php

namespace App\Console\Commands\Fleet;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

/**
 * Show Fleet Command - NetServa 3.0
 *
 * Displays the fleet hierarchy: VSite → VNode → VHost → VServ
 */
class ShfleetCommand extends Command
{
    protected $signature = 'shfleet
                            {vnode? : Filter by specific vnode (optional)}
                            {--vsite= : Filter by vsite name/slug}
                            {--stats : Show statistics summary}
                            {--simple : Simple tree output without colors or emojis}
                            {--compact : Compact output without colors}';

    protected $description = 'Show fleet infrastructure hierarchy (NetServa 3.0 CRUD: Read)';

    public function handle(): int
    {
        if ($this->option('simple')) {
            $data = $this->loadFleetData();
        } else {
            $data = spin(
                fn () => $this->loadFleetData(),
                'Loading fleet infrastructure...'
            );
        }

        if (empty($data['vsites']) || $data['vsites']->isEmpty()) {
            if (! $this->option('simple')) {
                note('No infrastructure found in the fleet.');
            }

            return self::SUCCESS;
        }

        $this->displayTree($data);

        if ($this->option('stats')) {
            $this->displayStats($data);
        }

        if (! $this->option('simple')) {
            outro('Fleet infrastructure tree complete');
        }

        return self::SUCCESS;
    }

    protected function loadFleetData(): array
    {
        $vsiteFilter = $this->option('vsite');
        $vnodeFilter = $this->argument('vnode');

        // Load vsites (top of hierarchy now)
        $vsitesQuery = DB::table('fleet_vsites')
            ->where('is_active', true)
            ->orderBy('name');

        if ($vsiteFilter) {
            $vsitesQuery->where(function ($q) use ($vsiteFilter) {
                $q->where('name', 'like', "%{$vsiteFilter}%")
                    ->orWhere('slug', 'like', "%{$vsiteFilter}%");
            });
        }

        $vsites = $vsitesQuery->get();
        $vsiteIds = $vsites->pluck('id')->toArray();

        // Load vnodes
        $vnodesQuery = DB::table('fleet_vnodes')
            ->whereIn('vsite_id', $vsiteIds)
            ->orderBy('name');

        if ($vnodeFilter) {
            $vnodesQuery->where(function ($q) use ($vnodeFilter) {
                $q->where('name', 'like', "%{$vnodeFilter}%")
                    ->orWhere('slug', 'like', "%{$vnodeFilter}%");
            });
        }

        $vnodes = $vnodesQuery->get()->groupBy('vsite_id');
        $vnodeIds = $vnodes->flatten()->pluck('id')->toArray();

        // Load vhosts
        $vhosts = DB::table('fleet_vhosts')
            ->whereIn('vnode_id', $vnodeIds)
            ->orderBy('domain')
            ->get()
            ->groupBy('vnode_id');

        $vhostIds = $vhosts->flatten()->pluck('id')->toArray();

        // Load vservs
        $vservs = DB::table('fleet_vservs')
            ->whereIn('vhost_id', $vhostIds)
            ->orderBy('service_name')
            ->get()
            ->groupBy('vhost_id');

        return [
            'vsites' => $vsites,
            'vnodes' => $vnodes,
            'vhosts' => $vhosts,
            'vservs' => $vservs,
        ];
    }

    protected function displayTree(array $data): void
    {
        if ($this->option('simple')) {
            $vsiteCount = $data['vsites']->count();
            $vnodeCount = $data['vnodes']->flatten()->count();
            $vhostCount = $data['vhosts']->flatten()->count();
            $vservCount = $data['vservs']->flatten()->count();

            $this->line('.');
            $this->line("├── {$vsiteCount} vsites");
            $this->line("├── {$vnodeCount} vnodes");
            $this->line("├── {$vhostCount} vhosts");
            $this->line("└── {$vservCount} vservs");
            $this->newLine();
        }

        foreach ($data['vsites'] as $vsite) {
            $this->renderVSite($vsite, $data);
        }
    }

    protected function renderVSite($vsite, array $data): void
    {
        $vnodes = $data['vnodes'][$vsite->id] ?? collect();
        $vnodeCount = $vnodes->count();

        if ($this->option('simple')) {
            $this->line($vsite->name);
        } else {
            $vsiteIcon = match ($vsite->technology ?? 'unknown') {
                'proxmox' => '📦',
                'incus', 'lxc' => '🐳',
                'kubernetes' => '☸️',
                'docker' => '🐋',
                'bare-metal', 'hardware' => '🖥️',
                'wireguard' => '🔐',
                'dns' => '🌐',
                'vps' => '☁️',
                default => '📁',
            };

            $vsiteInfo = sprintf(
                '%s <fg=cyan;options=bold>%s</> <fg=gray>(%s/%s)</> <fg=yellow>[%d vnodes]</>',
                $vsiteIcon,
                $vsite->name,
                $vsite->provider ?? 'unknown',
                $vsite->technology ?? 'unknown',
                $vnodeCount
            );

            info($vsiteInfo);
        }

        foreach ($vnodes as $index => $vnode) {
            $isLast = $index === $vnodes->count() - 1;
            $this->renderVNode($vnode, $data, $isLast ? '└──' : '├──', $isLast ? '    ' : '│   ');
        }

        $this->newLine();
    }

    protected function renderVNode($vnode, array $data, string $prefix, string $childPrefix): void
    {
        $vhosts = $data['vhosts'][$vnode->id] ?? collect();

        if ($this->option('simple')) {
            $this->line("{$prefix} {$vnode->name}");
        } else {
            $vhostCount = $vhosts->count();

            $vnodeIcon = match ($vnode->role ?? 'server') {
                'workstation' => '💻',
                'hypervisor' => '🖥️',
                'nameserver' => '🌐',
                'mailserver' => '📧',
                'webserver' => '🌍',
                'database' => '🗄️',
                'compute' => '⚙️',
                default => '🔧',
            };

            $vnodeInfo = sprintf(
                '%s <fg=blue;options=bold>%s</> <fg=gray>%s</> <fg=yellow>[%d vhosts]</>',
                $vnodeIcon,
                $vnode->name,
                $vnode->ip_address ? "({$vnode->ip_address})" : '',
                $vhostCount
            );

            $this->line("{$prefix} {$vnodeInfo}");
        }

        foreach ($vhosts as $index => $vhost) {
            $isLast = $index === $vhosts->count() - 1;
            $vhostPrefix = $childPrefix.($isLast ? '└──' : '├──');
            $vhostChildPrefix = $childPrefix.($isLast ? '    ' : '│   ');
            $this->renderVHost($vhost, $data, $vhostPrefix, $vhostChildPrefix);
        }
    }

    protected function renderVHost($vhost, array $data, string $prefix, string $childPrefix): void
    {
        $vservs = $data['vservs'][$vhost->id] ?? collect();

        if ($this->option('simple')) {
            $this->line("{$prefix} {$vhost->domain}");
        } else {
            $vservCount = $vservs->count();

            $vhostIcon = '🌐';
            $statusIcon = match ($vhost->status ?? 'unknown') {
                'active' => '✅',
                'inactive' => '⏸️',
                'error' => '❌',
                default => '❓',
            };

            $vhostInfo = sprintf(
                '%s <fg=magenta;options=bold>%s</> %s <fg=yellow>[%d services]</>',
                $vhostIcon,
                $vhost->domain,
                $statusIcon,
                $vservCount
            );

            $this->line("{$prefix} {$vhostInfo}");
        }

        if ($vservs->count() > 0) {
            foreach ($vservs as $index => $vserv) {
                $isLast = $index === $vservs->count() - 1;
                $vservPrefix = $childPrefix.($isLast ? '└──' : '├──');
                $this->renderVServ($vserv, $vservPrefix);
            }
        }
    }

    protected function renderVServ($vserv, string $prefix): void
    {
        if ($this->option('simple')) {
            $this->line("{$prefix} {$vserv->service_name}");
        } else {
            $servIcon = match ($vserv->service_name) {
                'nginx', 'apache', 'caddy' => '🌍',
                'mysql', 'mariadb', 'postgresql', 'postgres' => '🗄️',
                'redis', 'memcached' => '⚡',
                'php-fpm', 'php' => '🐘',
                'postfix', 'dovecot', 'exim' => '📧',
                'powerdns', 'pdns', 'bind' => '🌐',
                'ssh', 'sshd' => '🔐',
                'docker' => '🐋',
                'wireguard', 'wg' => '🔐',
                default => '⚙️',
            };

            $portInfo = $vserv->port ? ":{$vserv->port}" : '';
            $statusIcon = $vserv->status === 'running' ? '✅' : '⏸️';

            $servInfo = sprintf(
                '%s <fg=white>%s</><fg=gray>%s</> %s',
                $servIcon,
                $vserv->service_name,
                $portInfo,
                $statusIcon
            );

            $this->line("{$prefix} {$servInfo}");
        }
    }

    protected function displayStats(array $data): void
    {
        $this->newLine();
        info('📊 Fleet Statistics');

        $vsiteCount = $data['vsites']->count();
        $vnodeCount = $data['vnodes']->flatten()->count();
        $vhostCount = $data['vhosts']->flatten()->count();
        $vservCount = $data['vservs']->flatten()->count();

        $stats = [
            ['Component', 'Count'],
            ['VSites', $vsiteCount],
            ['VNodes', $vnodeCount],
            ['VHosts', $vhostCount],
            ['VServs', $vservCount],
            ['─────────', '─────'],
            ['Total', $vsiteCount + $vnodeCount + $vhostCount + $vservCount],
        ];

        table($stats);

        // Technology breakdown
        $techStats = $data['vsites']
            ->groupBy('technology')
            ->map->count()
            ->sortDesc();

        if ($techStats->isNotEmpty()) {
            $this->newLine();
            note('Technology Distribution:');
            foreach ($techStats as $tech => $count) {
                $this->line("  • {$tech}: {$count} vsites");
            }
        }

        // Role breakdown
        $roleStats = $data['vnodes']->flatten()
            ->groupBy('role')
            ->map->count()
            ->sortDesc();

        if ($roleStats->isNotEmpty()) {
            $this->newLine();
            note('VNode Role Distribution:');
            foreach ($roleStats as $role => $count) {
                $this->line("  • {$role}: {$count} vnodes");
            }
        }
    }
}
