<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Exceptions\AmbiguousVHostException;
use NetServa\Core\Exceptions\VHostNotFoundException;
use NetServa\Fleet\Models\FleetVhost;

/**
 * VHost Resolver Service
 *
 * Intelligently resolves VHost context from minimal input.
 * Supports smart resolution from just domain name up to full vsite/vnode/vhost.
 */
class VHostResolverService
{
    protected string $varPath;

    public function __construct()
    {
        $this->varPath = config('netserva-cli.paths.ns').'/var';
    }

    /**
     * Resolve vhost to its full context (vsite, vnode, vhost)
     *
     * @param  string  $vhost  Domain name to resolve
     * @param  string|null  $vnode  Optional vnode for disambiguation
     * @param  string|null  $vsite  Optional vsite for full control
     * @return array ['vsite' => '...', 'vnode' => '...', 'vhost' => '...', 'source' => '...']
     *
     * @throws VHostNotFoundException
     * @throws AmbiguousVHostException
     */
    public function resolveVHost(string $vhost, ?string $vnode = null, ?string $vsite = null): array
    {
        Log::info('Resolving VHost context', [
            'vhost' => $vhost,
            'vnode' => $vnode,
            'vsite' => $vsite,
        ]);

        // If all three provided, just validate and return
        if ($vsite && $vnode && $vhost) {
            return $this->validateFullContext($vsite, $vnode, $vhost);
        }

        // Try database first
        try {
            return $this->resolveFromDatabase($vhost, $vnode, $vsite);
        } catch (VHostNotFoundException $e) {
            Log::debug('VHost not found in database, trying filesystem', [
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to filesystem scanning
        return $this->resolveFromFilesystem($vhost, $vnode, $vsite);
    }

    /**
     * Resolve VHost context from database
     */
    protected function resolveFromDatabase(string $vhost, ?string $vnode = null, ?string $vsite = null): array
    {
        $query = FleetVhost::where('domain', $vhost)->with(['vnode.vsite']);

        // Apply filters if provided
        if ($vnode) {
            $query->whereHas('vnode', fn ($q) => $q->where('name', $vnode));
        }

        if ($vsite) {
            $query->whereHas('vnode.vsite', fn ($q) => $q->where('name', $vsite));
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            throw new VHostNotFoundException("VHost '{$vhost}' not found in database");
        }

        if ($results->count() === 1) {
            // Perfect - unique match
            $fleetVHost = $results->first();

            return [
                'vsite' => $fleetVHost->vnode->vsite->name,
                'vnode' => $fleetVHost->vnode->name,
                'vhost' => $fleetVHost->domain,
                'source' => 'database',
                'model' => $fleetVHost,
            ];
        }

        // Multiple matches - need more context
        $matches = $results->map(fn ($v) => [
            'vsite' => $v->vnode->vsite->name,
            'vnode' => $v->vnode->name,
            'vhost' => $v->domain,
            'path' => "{$v->vnode->vsite->name}/{$v->vnode->name}/{$v->domain}",
        ])->toArray();

        throw new AmbiguousVHostException(
            "Multiple matches found for '{$vhost}'. Please specify vnode or use:\n".
            collect($matches)->map(fn ($m) => "  • {$m['vnode']}/{$m['vhost']}")->join("\n"),
            $matches
        );
    }

    /**
     * Resolve VHost context from filesystem
     */
    protected function resolveFromFilesystem(string $vhost, ?string $vnode = null, ?string $vsite = null): array
    {
        if (! File::exists($this->varPath)) {
            throw new VHostNotFoundException("Var directory not found: {$this->varPath}");
        }

        $matches = [];

        // Search filesystem for matching vhost files
        foreach (File::directories($this->varPath) as $vsiteDir) {
            $vsiteName = basename($vsiteDir);

            // Skip if vsite filter provided and doesn't match
            if ($vsite && $vsiteName !== $vsite) {
                continue;
            }

            foreach (File::directories($vsiteDir) as $vnodeDir) {
                $vnodeName = basename($vnodeDir);

                // Skip if vnode filter provided and doesn't match
                if ($vnode && $vnodeName !== $vnode) {
                    continue;
                }

                $vhostFile = "{$vnodeDir}/{$vhost}";
                if (File::exists($vhostFile) && ! str_ends_with($vhostFile, '.conf')) {
                    $matches[] = [
                        'vsite' => $vsiteName,
                        'vnode' => $vnodeName,
                        'vhost' => $vhost,
                        'source' => 'filesystem',
                        'path' => $vhostFile,
                    ];
                }
            }
        }

        if (empty($matches)) {
            throw new VHostNotFoundException("VHost '{$vhost}' not found in filesystem: {$this->varPath}");
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        // Multiple matches found
        throw new AmbiguousVHostException(
            "Multiple filesystem matches for '{$vhost}'. Please specify vnode:\n".
            collect($matches)->map(fn ($m) => "  • {$m['vnode']}/{$m['vhost']} ({$m['vsite']})")->join("\n"),
            $matches
        );
    }

    /**
     * Validate full context when all three parameters provided
     */
    protected function validateFullContext(string $vsite, string $vnode, string $vhost): array
    {
        // Try database first
        $fleetVHost = FleetVhost::whereHas('vnode.vsite', fn ($q) => $q->where('name', $vsite))
            ->whereHas('vnode', fn ($q) => $q->where('name', $vnode))
            ->where('domain', $vhost)
            ->with(['vnode.vsite'])
            ->first();

        if ($fleetVHost) {
            return [
                'vsite' => $fleetVHost->vnode->vsite->name,
                'vnode' => $fleetVHost->vnode->name,
                'vhost' => $fleetVHost->domain,
                'source' => 'database',
                'model' => $fleetVHost,
            ];
        }

        // Try filesystem
        $vhostFile = "{$this->varPath}/{$vsite}/{$vnode}/{$vhost}";
        if (File::exists($vhostFile)) {
            return [
                'vsite' => $vsite,
                'vnode' => $vnode,
                'vhost' => $vhost,
                'source' => 'filesystem',
                'path' => $vhostFile,
            ];
        }

        throw new VHostNotFoundException("VHost not found: {$vsite}/{$vnode}/{$vhost}");
    }

    /**
     * Search for VHosts by partial domain match
     */
    public function searchVHosts(string $partialDomain, int $limit = 10): Collection
    {
        // Search database first
        $dbResults = FleetVhost::where('domain', 'like', "%{$partialDomain}%")
            ->with(['vnode.vsite'])
            ->limit($limit)
            ->get()
            ->map(fn ($v) => [
                'vsite' => $v->vnode->vsite->name,
                'vnode' => $v->vnode->name,
                'vhost' => $v->domain,
                'source' => 'database',
                'path' => "{$v->vnode->vsite->name}/{$v->vnode->name}/{$v->domain}",
                'model' => $v,
            ]);

        // Add filesystem results if needed
        if ($dbResults->count() < $limit && File::exists($this->varPath)) {
            $fsResults = collect();

            foreach (File::directories($this->varPath) as $vsiteDir) {
                foreach (File::directories($vsiteDir) as $vnodeDir) {
                    foreach (File::files($vnodeDir) as $file) {
                        $filename = basename($file);
                        if (! str_ends_with($filename, '.conf') &&
                            str_contains($filename, $partialDomain)) {
                            $fsResults->push([
                                'vsite' => basename($vsiteDir),
                                'vnode' => basename($vnodeDir),
                                'vhost' => $filename,
                                'source' => 'filesystem',
                                'path' => $file,
                            ]);
                        }
                    }
                }
            }

            return $dbResults->concat($fsResults)->take($limit);
        }

        return $dbResults;
    }

    /**
     * Get all VHosts for a specific vnode
     */
    public function getVHostsForVNode(string $vnode, ?string $vsite = null): Collection
    {
        // Try database first
        $query = FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $vnode))
            ->with(['vnode.vsite']);

        if ($vsite) {
            $query->whereHas('vnode.vsite', fn ($q) => $q->where('name', $vsite));
        }

        $dbResults = $query->get()->map(fn ($v) => [
            'vsite' => $v->vnode->vsite->name,
            'vnode' => $v->vnode->name,
            'vhost' => $v->domain,
            'source' => 'database',
            'model' => $v,
        ]);

        // Add filesystem results
        $fsResults = collect();
        if (File::exists($this->varPath)) {
            foreach (File::directories($this->varPath) as $vsiteDir) {
                if ($vsite && basename($vsiteDir) !== $vsite) {
                    continue;
                }

                $vnodeDir = "{$vsiteDir}/{$vnode}";
                if (File::exists($vnodeDir) && File::isDirectory($vnodeDir)) {
                    foreach (File::files($vnodeDir) as $file) {
                        $filename = basename($file);
                        if (! str_ends_with($filename, '.conf')) {
                            $fsResults->push([
                                'vsite' => basename($vsiteDir),
                                'vnode' => $vnode,
                                'vhost' => $filename,
                                'source' => 'filesystem',
                                'path' => $file,
                            ]);
                        }
                    }
                }
            }
        }

        return $dbResults->concat($fsResults)->unique('vhost');
    }

    /**
     * Check if a VHost exists (for validation)
     */
    public function vhostExists(string $vhost, ?string $vnode = null, ?string $vsite = null): bool
    {
        try {
            $this->resolveVHost($vhost, $vnode, $vsite);

            return true;
        } catch (VHostNotFoundException|AmbiguousVHostException $e) {
            return false;
        }
    }
}
