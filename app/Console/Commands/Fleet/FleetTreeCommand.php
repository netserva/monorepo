<?php

namespace App\Console\Commands\Fleet;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class FleetTreeCommand extends Command
{
    protected $signature = 'fleet:tree
                            {--venue= : Filter by venue name/slug}
                            {--vsite= : Filter by vsite name/slug}
                            {--vnode= : Filter by vnode name/slug}
                            {--stats : Show statistics summary}
                            {--simple : Simple tree output without colors or emojis}
                            {--compact : Compact output without colors}';

    protected $description = 'Display the complete fleet infrastructure hierarchy as a tree';

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

        if (empty($data['venues'])) {
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
        $venueFilter = $this->option('venue');
        $vsiteFilter = $this->option('vsite');
        $vnodeFilter = $this->option('vnode');

        // Load venues
        $venuesQuery = DB::table('fleet_venues')
            ->where('is_active', true)
            ->orderBy('name');

        if ($venueFilter) {
            $venuesQuery->where(function ($q) use ($venueFilter) {
                $q->where('name', 'like', "%{$venueFilter}%")
                    ->orWhere('slug', 'like', "%{$venueFilter}%");
            });
        }

        $venues = $venuesQuery->get();

        // Load all related data
        $venueIds = $venues->pluck('id')->toArray();

        $vsites = DB::table('fleet_vsites')
            ->whereIn('venue_id', $venueIds)
            ->when($vsiteFilter, fn ($q) => $q->where('name', 'like', "%{$vsiteFilter}%")
                ->orWhere('slug', 'like', "%{$vsiteFilter}%"))
            ->orderBy('name')
            ->get()
            ->groupBy('venue_id');

        $vsiteIds = $vsites->flatten()->pluck('id')->toArray();

        $vnodes = DB::table('fleet_vnodes')
            ->whereIn('vsite_id', $vsiteIds)
            ->when($vnodeFilter, fn ($q) => $q->where('name', 'like', "%{$vnodeFilter}%")
                ->orWhere('slug', 'like', "%{$vnodeFilter}%"))
            ->orderBy('name')
            ->get()
            ->groupBy('vsite_id');

        $vnodeIds = $vnodes->flatten()->pluck('id')->toArray();

        $vhosts = DB::table('fleet_vhosts')
            ->whereIn('vnode_id', $vnodeIds)
            ->orderBy('domain')
            ->get()
            ->groupBy('vnode_id');

        $vhostIds = $vhosts->flatten()->pluck('id')->toArray();

        $vservs = DB::table('fleet_vservs')
            ->whereIn('vhost_id', $vhostIds)
            ->orderBy('service_name')
            ->get()
            ->groupBy('vhost_id');

        return [
            'venues' => $venues,
            'vsites' => $vsites,
            'vnodes' => $vnodes,
            'vhosts' => $vhosts,
            'vservs' => $vservs,
        ];
    }

    protected function displayTree(array $data): void
    {
        if ($this->option('simple')) {
            $venueCount = $data['venues']->count();
            $vsiteCount = $data['vsites']->flatten()->count();
            $vnodeCount = $data['vnodes']->flatten()->count();
            $vhostCount = $data['vhosts']->flatten()->count();
            $vservCount = $data['vservs']->flatten()->count();

            $this->line('.');
            $this->line("├── {$venueCount} venues");
            $this->line("├── {$vsiteCount} vsites");
            $this->line("├── {$vnodeCount} vnodes");
            $this->line("├── {$vhostCount} vhosts");
            $this->line("└── {$vservCount} vservs");
            $this->newLine();
        }

        foreach ($data['venues'] as $venue) {
            $this->renderVenue($venue, $data);
        }
    }

    protected function renderVenue($venue, array $data): void
    {
        $vsites = $data['vsites'][$venue->id] ?? collect();
        $vsiteCount = $vsites->count();

        if ($this->option('simple')) {
            $this->line($venue->name);
        } else {
            $venueIcon = '🌍';
            $venueInfo = sprintf(
                '%s <fg=cyan;options=bold>%s</> <fg=gray>(%s)</> <fg=yellow>[%d vsites]</>',
                $venueIcon,
                $venue->name,
                $venue->provider ?? 'unknown',
                $vsiteCount
            );

            info($venueInfo);
        }

        foreach ($vsites as $index => $vsite) {
            $isLast = $index === $vsites->count() - 1;
            $this->renderVSite($vsite, $data, $isLast ? '└──' : '├──', $isLast ? '    ' : '│   ');
        }

        $this->newLine();
    }

    protected function renderVSite($vsite, array $data, string $prefix, string $childPrefix): void
    {
        $vnodes = $data['vnodes'][$vsite->id] ?? collect();

        if ($this->option('simple')) {
            $this->line("{$prefix} {$vsite->name}");
        } else {
            $vnodeCount = $vnodes->count();

            $vsiteIcon = match ($vsite->technology) {
                'proxmox' => '📦',
                'incus', 'lxc' => '🐳',
                'kubernetes' => '☸️',
                'docker' => '🐋',
                'bare-metal' => '🖥️',
                'wireguard' => '🔐',
                'dns' => '🌐',
                default => '📁',
            };

            $vsiteInfo = sprintf(
                '%s <fg=green;options=bold>%s</> <fg=gray>(%s)</> <fg=yellow>[%d vnodes]</>',
                $vsiteIcon,
                $vsite->name,
                $vsite->technology,
                $vnodeCount
            );

            $this->line("{$prefix} {$vsiteInfo}");
        }

        foreach ($vnodes as $index => $vnode) {
            $isLast = $index === $vnodes->count() - 1;
            $vnodePrefix = $childPrefix.($isLast ? '└──' : '├──');
            $vnodeChildPrefix = $childPrefix.($isLast ? '    ' : '│   ');
            $this->renderVNode($vnode, $data, $vnodePrefix, $vnodeChildPrefix);
        }
    }

    protected function renderVNode($vnode, array $data, string $prefix, string $childPrefix): void
    {
        $vhosts = $data['vhosts'][$vnode->id] ?? collect();

        if ($this->option('simple')) {
            $this->line("{$prefix} {$vnode->name}");
        } else {
            $vhostCount = $vhosts->count();

            $vnodeIcon = match ($vnode->role) {
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
            $statusIcon = match ($vhost->status) {
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

        $venueCount = $data['venues']->count();
        $vsiteCount = $data['vsites']->flatten()->count();
        $vnodeCount = $data['vnodes']->flatten()->count();
        $vhostCount = $data['vhosts']->flatten()->count();
        $vservCount = $data['vservs']->flatten()->count();

        $stats = [
            ['Component', 'Count'],
            ['Venues', $venueCount],
            ['VSites', $vsiteCount],
            ['VNodes', $vnodeCount],
            ['VHosts', $vhostCount],
            ['VServs', $vservCount],
            ['─────────', '─────'],
            ['Total', $venueCount + $vsiteCount + $vnodeCount + $vhostCount + $vservCount],
        ];

        table($stats);

        // Technology breakdown
        $techStats = $data['vsites']->flatten()
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
