<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cms\Services\CmsExportService;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

/**
 * CMS Export Command
 *
 * Exports all CMS content and media to a portable ZIP file
 */
class ExportCommand extends Command
{
    protected $signature = 'cms:export
                            {--output= : Output file path (default: storage/app/cms-export-TIMESTAMP.zip)}
                            {--include-drafts : Include unpublished posts and pages}
                            {--include-deleted : Include soft-deleted content}';

    protected $description = 'Export all CMS content and media to a ZIP file';

    public function handle(CmsExportService $exportService): int
    {
        $this->components->info('Exporting CMS content...');

        // Determine output path
        $outputPath = $this->option('output') ?? storage_path('app/cms-export-'.now()->format('Y-m-d-His').'.zip');

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Run export with spinner
        $result = spin(
            fn () => $exportService->export(
                $outputPath,
                $this->option('include-drafts'),
                $this->option('include-deleted')
            ),
            'Exporting CMS content and media...'
        );

        // Display results
        $this->components->info('');
        $this->components->success('CMS export completed successfully!');
        $this->components->info('');

        $this->components->twoColumnDetail('Output file', $result['output_path']);
        $this->components->twoColumnDetail('File size', $this->formatBytes($result['size']));
        $this->components->info('');

        $this->components->info('Export statistics:');
        $this->components->twoColumnDetail('  Pages', (string) $result['stats']['pages']);
        $this->components->twoColumnDetail('  Posts', (string) $result['stats']['posts']);
        $this->components->twoColumnDetail('  Categories', (string) $result['stats']['categories']);
        $this->components->twoColumnDetail('  Tags', (string) $result['stats']['tags']);
        $this->components->twoColumnDetail('  Menus', (string) $result['stats']['menus']);
        $this->components->twoColumnDetail('  Media files', (string) $result['stats']['media_files']);

        $this->components->info('');
        info('You can now import this file using: php artisan cms:import '.$result['output_path']);

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }
}
