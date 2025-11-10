<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cms\Services\ThemeService;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

/**
 * Discover CMS Themes from Filesystem
 *
 * Scans the resources/themes directory and registers any themes
 * that have a valid theme.json manifest file.
 */
class ThemeDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:theme-discover
                          {--fresh : Delete existing themes before discovering}';

    /**
     * The console command description.
     */
    protected $description = 'Discover and register CMS themes from filesystem';

    /**
     * Execute the console command.
     */
    public function handle(ThemeService $themeService): int
    {
        if ($this->option('fresh')) {
            info('Removing existing themes from database...');
            \NetServa\Cms\Models\Theme::query()->delete();
        }

        $count = spin(
            fn () => $themeService->discover(),
            'Discovering themes from filesystem...'
        );

        if ($count === 0) {
            $this->warn('No themes found in resources/themes/');

            return self::SUCCESS;
        }

        $this->info("✓ Discovered {$count} theme(s)");

        // Display discovered themes
        $themes = $themeService->all();
        $data = $themes->map(fn ($theme) => [
            'Theme' => $theme->display_name,
            'Slug' => $theme->name,
            'Version' => $theme->version,
            'Active' => $theme->is_active ? '✓' : '',
        ])->toArray();

        table(['Theme', 'Slug', 'Version', 'Active'], $data);

        return self::SUCCESS;
    }
}
