<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cms\Services\ThemeService;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

/**
 * List All CMS Themes
 *
 * Displays a table of all registered themes with their metadata.
 */
class ThemeListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:theme-list
                          {--active : Show only the active theme}';

    /**
     * The console command description.
     */
    protected $description = 'List all registered CMS themes';

    /**
     * Execute the console command.
     */
    public function handle(ThemeService $themeService): int
    {
        if ($this->option('active')) {
            $theme = $themeService->getActive();

            info("Active Theme: {$theme->display_name}");

            table(
                ['Property', 'Value'],
                [
                    ['Name', $theme->display_name],
                    ['Slug', $theme->name],
                    ['Version', $theme->version],
                    ['Author', $theme->author ?? 'N/A'],
                    ['Parent', $theme->parent_theme ?? 'None'],
                    ['Path', $theme->path()],
                    ['Colors', count($theme->colors())],
                    ['Templates', collect($theme->manifest['templates'] ?? [])->sum(fn ($t) => count($t))],
                ]
            );

            return self::SUCCESS;
        }

        $themes = $themeService->all();

        if ($themes->isEmpty()) {
            $this->warn('No themes found. Run cms:theme-discover to scan for themes.');

            return self::SUCCESS;
        }

        $data = $themes->map(fn ($theme) => [
            'Name' => $theme->display_name,
            'Slug' => $theme->name,
            'Version' => $theme->version,
            'Author' => $theme->author ?? 'N/A',
            'Parent' => $theme->parent_theme ?? 'None',
            'Active' => $theme->is_active ? '✓' : '',
        ])->toArray();

        table(['Name', 'Slug', 'Version', 'Author', 'Parent', 'Active'], $data);

        $this->info("\nTotal: {$themes->count()} theme(s)");

        return self::SUCCESS;
    }
}
