<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Services\ThemeService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\spin;

/**
 * Activate a CMS Theme
 *
 * Switches the active theme to the specified theme.
 */
class ThemeActivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:theme-activate {theme? : Theme slug to activate}';

    /**
     * The console command description.
     */
    protected $description = 'Activate a CMS theme';

    /**
     * Execute the console command.
     */
    public function handle(ThemeService $themeService): int
    {
        $themeName = $this->argument('theme');

        // If no theme provided, show interactive search
        if (! $themeName) {
            $themes = Theme::query()->orderBy('name')->get();

            if ($themes->isEmpty()) {
                error('No themes found. Run cms:theme-discover first.');

                return self::FAILURE;
            }

            $themeName = search(
                label: 'Which theme would you like to activate?',
                options: fn (string $value) => strlen($value) > 0
                    ? $themes->filter(fn ($theme) => str_contains(
                        strtolower($theme->name.' '.$theme->display_name),
                        strtolower($value)
                    ))->mapWithKeys(fn ($theme) => [
                        $theme->name => $theme->display_name.' ('.$theme->name.')',
                    ])->toArray()
                    : $themes->mapWithKeys(fn ($theme) => [
                        $theme->name => $theme->display_name.' ('.$theme->name.')',
                    ])->toArray(),
                placeholder: 'Search themes...',
            );
        }

        // Check if theme exists
        $theme = Theme::where('name', $themeName)->first();

        if (! $theme) {
            error("Theme '{$themeName}' not found");

            return self::FAILURE;
        }

        // Check if already active
        if ($theme->is_active) {
            info("Theme '{$theme->display_name}' is already active");

            return self::SUCCESS;
        }

        // Activate theme
        spin(
            fn () => $themeService->activate($themeName),
            "Activating theme '{$theme->display_name}'..."
        );

        $this->info("✓ Theme '{$theme->display_name}' activated successfully");

        return self::SUCCESS;
    }
}
