<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\BinaryLaneConfigurationException;
use NetServa\Core\Services\BinaryLaneService;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'bl')]
class BinaryLaneCommand extends BaseNetServaCommand
{
    protected $signature = 'bl
                           {action : Action to perform (list|show|sizes|images|regions|create|delete|test)}
                           {server-id? : Server ID for show/delete operations}
                           {--region= : Region for create operation}
                           {--size= : Size for create operation}
                           {--image= : Image for create operation}
                           {--name= : Name for create operation}
                           {--format=table : Output format (table|json)}
                           {--dry-run : Show what would be done without executing}
                           {--verbose : Show detailed output}';

    protected $description = 'Manage BinaryLane VPS instances with comprehensive API operations';

    public function __construct(
        protected BinaryLaneService $binaryLaneService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $action = $this->argument('action');

            try {
                return match ($action) {
                    'list' => $this->handleList(),
                    'show' => $this->handleShow(),
                    'sizes' => $this->handleSizes(),
                    'images' => $this->handleImages(),
                    'regions' => $this->handleRegions(),
                    'create' => $this->handleCreate(),
                    'delete' => $this->handleDelete(),
                    'test' => $this->handleTest(),
                    default => $this->handleInvalidAction($action),
                };
            } catch (BinaryLaneConfigurationException $e) {
                $this->error('❌ Configuration Error: '.$e->getMessage());
                $this->newLine();
                $this->line('💡 <fg=yellow>Setup Instructions:</fg=yellow>');
                $this->line('   1. Set BINARYLANE_API_TOKEN in your .env file');
                $this->line('   2. Get your API token from: https://panel.binarylane.com.au/account/api');
                $this->line('   3. Test connection: php artisan bl test');

                return 1;
            } catch (\Exception $e) {
                $this->error('❌ BinaryLane API Error: '.$e->getMessage());

                return 1;
            }
        });
    }

    protected function handleList(): int
    {
        info('📋 Listing BinaryLane servers...');

        $servers = $this->binaryLaneService->listServers();

        if (empty($servers['servers'])) {
            info('✅ No servers found in your BinaryLane account');

            return 0;
        }

        $this->displayServers($servers['servers']);

        return 0;
    }

    protected function handleShow(): int
    {
        $serverId = $this->argument('server-id');

        if (! $serverId) {
            // Prompt user to select from available servers
            $serverId = $this->selectServer();
            if (! $serverId) {
                return 1;
            }
        }

        info("🔍 Getting server details for ID: {$serverId}");

        $server = $this->binaryLaneService->getServer($serverId);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($server, JSON_PRETTY_PRINT));

            return 0;
        }

        $this->displayServerDetails($server['server']);

        return 0;
    }

    protected function handleSizes(): int
    {
        info('📊 Listing available VPS sizes...');

        $sizes = $this->binaryLaneService->listSizes();

        if (empty($sizes['sizes'])) {
            warning('⚠️ No sizes available');

            return 0;
        }

        $this->displaySizes($sizes['sizes']);

        return 0;
    }

    protected function handleImages(): int
    {
        info('💿 Listing available OS images...');

        $images = $this->binaryLaneService->listImages();

        if (empty($images['images'])) {
            warning('⚠️ No images available');

            return 0;
        }

        $this->displayImages($images['images']);

        return 0;
    }

    protected function handleRegions(): int
    {
        info('🌏 Listing available regions...');

        $regions = $this->binaryLaneService->listRegions();

        if (empty($regions['regions'])) {
            warning('⚠️ No regions available');

            return 0;
        }

        $this->displayRegions($regions['regions']);

        return 0;
    }

    protected function handleCreate(): int
    {
        info('🚀 Creating new BinaryLane VPS...');

        // Get creation parameters
        $data = $this->getCreateParameters();

        if ($this->option('dry-run')) {
            $this->dryRun('Create BinaryLane VPS', [
                "Name: {$data['name']}",
                "Size: {$data['size']}",
                "Image: {$data['image']}",
                "Region: {$data['region']}",
            ]);

            return 0;
        }

        $confirmed = confirm(
            label: "Create VPS '{$data['name']}' in {$data['region']} with {$data['size']}?",
            default: false
        );

        if (! $confirmed) {
            info('❌ VPS creation cancelled');

            return 0;
        }

        info('⏳ Creating VPS... This may take a few minutes.');

        $result = $this->binaryLaneService->createServer($data);

        $this->line('');
        info('✅ VPS creation initiated successfully!');
        $this->line("Server ID: {$result['server']['id']}");
        $this->line("Name: {$result['server']['name']}");
        $this->line("Status: {$result['server']['status']}");
        $this->newLine();
        $this->line('💡 Use "php artisan bl show '.$result['server']['id'].'" to check status');

        return 0;
    }

    protected function handleDelete(): int
    {
        $serverId = $this->argument('server-id');

        if (! $serverId) {
            $serverId = $this->selectServer();
            if (! $serverId) {
                return 1;
            }
        }

        // Get server details for confirmation
        $server = $this->binaryLaneService->getServer($serverId);
        $serverName = $server['server']['name'] ?? $serverId;

        if ($this->option('dry-run')) {
            $this->dryRun('Delete BinaryLane VPS', [
                "Server ID: {$serverId}",
                "Name: {$serverName}",
            ]);

            return 0;
        }

        warning("⚠️ This will PERMANENTLY DELETE the VPS: {$serverName}");
        $this->line('This action cannot be undone!');

        $confirmed = confirm(
            label: "Are you sure you want to delete '{$serverName}'?",
            default: false
        );

        if (! $confirmed) {
            info('❌ VPS deletion cancelled');

            return 0;
        }

        info('🗑️ Deleting VPS...');

        $this->binaryLaneService->deleteServer($serverId);

        info("✅ VPS '{$serverName}' has been deleted successfully");

        return 0;
    }

    protected function handleTest(): int
    {
        info('🔌 Testing BinaryLane API connection...');

        $result = $this->binaryLaneService->testConnection();

        if ($result['success']) {
            info('✅ '.$result['message']);
            if (isset($result['account']['email'])) {
                $this->line("Account: {$result['account']['email']}");
            }
        } else {
            $this->error('❌ '.$result['message']);

            return 1;
        }

        return 0;
    }

    protected function handleInvalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->newLine();
        $this->line('<fg=yellow>Available actions:</fg=yellow>');
        $this->line('  • list     - List all VPS instances');
        $this->line('  • show     - Show detailed VPS information');
        $this->line('  • sizes    - List available VPS sizes');
        $this->line('  • images   - List available OS images');
        $this->line('  • regions  - List available regions');
        $this->line('  • create   - Create new VPS instance');
        $this->line('  • delete   - Delete VPS instance');
        $this->line('  • test     - Test API connection');

        return 1;
    }

    protected function selectServer(): ?string
    {
        try {
            $servers = $this->binaryLaneService->listServers();

            if (empty($servers['servers'])) {
                $this->error('❌ No servers found in your account');

                return null;
            }

            $options = [];
            foreach ($servers['servers'] as $server) {
                $formatted = $this->binaryLaneService->formatServerForDisplay($server);
                $options[$server['id']] = "{$formatted['name']} ({$formatted['ip']}) - {$formatted['status']}";
            }

            return select(
                label: 'Select a server:',
                options: $options
            );
        } catch (\Exception $e) {
            $this->error('❌ Failed to list servers: '.$e->getMessage());

            return null;
        }
    }

    protected function getCreateParameters(): array
    {
        $data = [];

        // Get name
        $data['name'] = $this->option('name') ?: text(
            label: 'Server name:',
            placeholder: 'my-server',
            required: true,
            validate: fn ($value) => preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]{0,62}[a-zA-Z0-9]$/', $value)
                ? null : 'Name must be 1-64 characters, alphanumeric with dots/hyphens'
        );

        // Get region
        if ($this->option('region')) {
            $data['region'] = $this->option('region');
        } else {
            $regions = $this->binaryLaneService->listRegions();
            $regionOptions = [];
            foreach ($regions['regions'] as $region) {
                if ($region['available']) {
                    $formatted = $this->binaryLaneService->formatRegionForDisplay($region);
                    $regionOptions[$region['slug']] = "{$formatted['name']} ({$formatted['slug']})";
                }
            }

            $data['region'] = select(
                label: 'Select region:',
                options: $regionOptions
            );
        }

        // Get size
        if ($this->option('size')) {
            $data['size'] = $this->option('size');
        } else {
            $sizes = $this->binaryLaneService->listSizes();
            $sizeOptions = [];
            foreach ($sizes['sizes'] as $size) {
                $formatted = $this->binaryLaneService->formatSizeForDisplay($size);
                $sizeOptions[$size['slug']] = "{$formatted['slug']} - {$formatted['vcpus']} CPU, {$formatted['memory']}, {$formatted['disk']} ({$formatted['monthly']}/month)";
            }

            $data['size'] = select(
                label: 'Select VPS size:',
                options: $sizeOptions
            );
        }

        // Get image
        if ($this->option('image')) {
            $data['image'] = $this->option('image');
        } else {
            $images = $this->binaryLaneService->listImages();
            $imageOptions = [];
            foreach ($images['images'] as $image) {
                if ($image['status'] === 'available') {
                    $formatted = $this->binaryLaneService->formatImageForDisplay($image);
                    $imageOptions[$image['slug']] = "{$formatted['name']} ({$formatted['slug']})";
                }
            }

            $data['image'] = select(
                label: 'Select OS image:',
                options: $imageOptions
            );
        }

        return $data;
    }

    protected function displayServers(array $servers): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($servers, JSON_PRETTY_PRINT));

            return;
        }

        $headers = ['ID', 'Name', 'IP Address', 'Status', 'Size', 'Region'];
        $rows = [];

        foreach ($servers as $server) {
            $formatted = $this->binaryLaneService->formatServerForDisplay($server);
            $rows[] = [
                $formatted['id'],
                $formatted['name'],
                $formatted['ip'],
                $formatted['status'],
                $formatted['size'],
                $formatted['region'],
            ];
        }

        table($headers, $rows);
    }

    protected function displayServerDetails(array $server): void
    {
        $formatted = $this->binaryLaneService->formatServerForDisplay($server);

        $this->line('');
        $this->line('<fg=blue>🖥️  Server Details</>');
        $this->line(str_repeat('=', 40));
        $this->line("ID: {$formatted['id']}");
        $this->line("Name: {$formatted['name']}");
        $this->line("IP Address: {$formatted['ip']}");
        $this->line("Status: {$formatted['status']}");
        $this->line("Size: {$formatted['size']}");
        $this->line("Region: {$formatted['region']}");

        if (isset($server['created_at'])) {
            $this->line("Created: {$server['created_at']}");
        }

        if (isset($server['image']['name'])) {
            $this->line("Image: {$server['image']['name']}");
        }
    }

    protected function displaySizes(array $sizes): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($sizes, JSON_PRETTY_PRINT));

            return;
        }

        $headers = ['Slug', 'vCPUs', 'Memory', 'Disk', 'Hourly', 'Monthly'];
        $rows = [];

        foreach ($sizes as $size) {
            $formatted = $this->binaryLaneService->formatSizeForDisplay($size);
            $rows[] = [
                $formatted['slug'],
                $formatted['vcpus'],
                $formatted['memory'],
                $formatted['disk'],
                $formatted['hourly'],
                $formatted['monthly'],
            ];
        }

        table($headers, $rows);
    }

    protected function displayImages(array $images): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($images, JSON_PRETTY_PRINT));

            return;
        }

        $headers = ['Slug', 'Name', 'Type', 'Status'];
        $rows = [];

        foreach ($images as $image) {
            $formatted = $this->binaryLaneService->formatImageForDisplay($image);
            $rows[] = [
                $formatted['slug'],
                $formatted['name'],
                $formatted['type'],
                $formatted['status'],
            ];
        }

        table($headers, $rows);
    }

    protected function displayRegions(array $regions): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($regions, JSON_PRETTY_PRINT));

            return;
        }

        $headers = ['Slug', 'Name', 'Location', 'Available'];
        $rows = [];

        foreach ($regions as $region) {
            $formatted = $this->binaryLaneService->formatRegionForDisplay($region);
            $rows[] = [
                $formatted['slug'],
                $formatted['name'],
                $formatted['location'],
                $formatted['available'],
            ];
        }

        table($headers, $rows);
    }
}
