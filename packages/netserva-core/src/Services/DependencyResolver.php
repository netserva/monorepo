<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Log;

class DependencyResolver
{
    /**
     * Resolve plugin dependencies and return plugins in loading order
     */
    public function resolve(array $enabledPlugins): array
    {
        $dependencyGraph = $this->buildDependencyGraph($enabledPlugins);

        return $this->topologicalSort($dependencyGraph);
    }

    /**
     * Build dependency graph for enabled plugins
     */
    protected function buildDependencyGraph(array $enabledPlugins): array
    {
        $graph = [];

        foreach ($enabledPlugins as $pluginId => $pluginClass) {
            if (! class_exists($pluginClass)) {
                Log::warning("Plugin class {$pluginClass} not found, skipping {$pluginId}");

                continue;
            }

            try {
                $plugin = new $pluginClass;
                $dependencies = method_exists($plugin, 'getDependencies') ? $plugin->getDependencies() : [];

                // Only include dependencies that are also enabled
                $validDependencies = array_intersect($dependencies, array_keys($enabledPlugins));
                $graph[$pluginId] = $validDependencies;
            } catch (\Exception $e) {
                Log::error("Failed to analyze dependencies for {$pluginId}: ".$e->getMessage());
                $graph[$pluginId] = [];
            }
        }

        return $graph;
    }

    /**
     * Perform topological sort on dependency graph
     */
    protected function topologicalSort(array $graph): array
    {
        $result = [];
        $visiting = [];
        $visited = [];

        foreach (array_keys($graph) as $node) {
            if (! isset($visited[$node])) {
                $this->topologicalSortVisit($graph, $node, $visiting, $visited, $result);
            }
        }

        return array_reverse($result);
    }

    /**
     * Visit node in topological sort
     */
    protected function topologicalSortVisit(array $graph, string $node, array &$visiting, array &$visited, array &$result): void
    {
        if (isset($visiting[$node])) {
            Log::error("Circular dependency detected involving plugin: {$node}");

            return;
        }

        if (isset($visited[$node])) {
            return;
        }

        $visiting[$node] = true;

        foreach ($graph[$node] ?? [] as $dependency) {
            if (isset($graph[$dependency])) {
                $this->topologicalSortVisit($graph, $dependency, $visiting, $visited, $result);
            }
        }

        unset($visiting[$node]);
        $visited[$node] = true;
        $result[] = $node;
    }

    /**
     * Validate that all dependencies can be satisfied
     */
    public function validateDependencies(array $enabledPlugins): array
    {
        $errors = [];
        $availablePlugins = array_keys($enabledPlugins);

        foreach ($enabledPlugins as $pluginId => $pluginClass) {
            if (! class_exists($pluginClass)) {
                $errors[] = "Plugin class {$pluginClass} not found for {$pluginId}";

                continue;
            }

            try {
                $plugin = new $pluginClass;
                if (method_exists($plugin, 'getDependencies')) {
                    $dependencies = $plugin->getDependencies();

                    foreach ($dependencies as $dependencyId) {
                        if (! in_array($dependencyId, $availablePlugins)) {
                            $errors[] = "Plugin {$pluginId} requires {$dependencyId} which is not enabled";
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to validate dependencies for {$pluginId}: ".$e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Check if plugins can be disabled without breaking dependencies
     */
    public function canDisable(string $pluginId, array $enabledPlugins): array
    {
        $dependents = [];

        foreach ($enabledPlugins as $currentPluginId => $pluginClass) {
            if ($currentPluginId === $pluginId) {
                continue;
            }

            if (! class_exists($pluginClass)) {
                continue;
            }

            try {
                $plugin = new $pluginClass;
                if (method_exists($plugin, 'getDependencies')) {
                    $dependencies = $plugin->getDependencies();
                    if (in_array($pluginId, $dependencies)) {
                        $dependents[] = $currentPluginId;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to check dependencies for {$currentPluginId}: ".$e->getMessage());
            }
        }

        return $dependents;
    }

    /**
     * Get the optimal loading order for a set of plugins
     */
    public function getLoadingOrder(array $plugins): array
    {
        $graph = [];

        // Build simplified graph with just plugin IDs
        foreach ($plugins as $pluginId) {
            $graph[$pluginId] = [];
        }

        // Add dependencies
        foreach ($plugins as $pluginId) {
            // This would need to be enhanced to get actual dependencies
            // For now, we'll use a basic ordering
        }

        // Return plugins with core plugins first
        $corePlugins = ['ns-core', 'ns-plugins'];
        $otherPlugins = array_diff($plugins, $corePlugins);

        return array_merge(
            array_intersect($corePlugins, $plugins),
            $otherPlugins
        );
    }
}
