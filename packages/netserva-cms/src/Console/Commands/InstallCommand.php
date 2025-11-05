<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * CMS Installation Command
 *
 * Automates post-install setup for standalone CMS deployments
 */
class InstallCommand extends Command
{
    protected $signature = 'netserva-cms:install
                            {--seed : Run database seeder}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Install and configure NetServa CMS';

    public function handle(): int
    {
        $this->components->info('Installing NetServa CMS...');

        // Step 1: Check routes/web.php
        $this->handleWebRoutes();

        // Step 2: Check DatabaseSeeder
        $this->handleDatabaseSeeder();

        // Step 3: Run migrations
        if ($this->option('force') || confirm('Run database migrations?', default: true)) {
            $this->call('migrate', ['--force' => true]);
        }

        // Step 4: Run seeder
        if ($this->option('seed') || ($this->option('force') ? true : confirm('Seed default CMS content?', default: true))) {
            $this->call('db:seed', [
                '--class' => 'NetServa\\Cms\\Database\\Seeders\\NetServaCmsSeeder',
                '--force' => true,
            ]);
        }

        // Step 5: Check Filament installation
        $this->handleFilamentSetup();

        // Step 6: Configure frontend assets
        $this->configureFrontendAssets();

        // Step 7: Create admin user
        if ($this->option('force') || confirm('Create admin user?', default: true)) {
            $this->createAdminUser();
        }

        // Step 8: Build assets
        if ($this->option('force') || confirm('Build frontend assets? (requires npm)', default: false)) {
            $this->call('filament:assets');
            $this->components->task('Running npm install', fn () => $this->executeShellCommand('npm install'));
            $this->components->task('Running npm run build', fn () => $this->executeShellCommand('npm run build'));
        }

        $this->components->info('');
        $this->components->success('NetServa CMS installed successfully!');
        $this->components->info('');
        $this->components->bulletList([
            'Frontend: '.config('app.url'),
            'Admin Panel: '.config('app.url').'/admin',
            'Login with the credentials you just created',
        ]);

        return self::SUCCESS;
    }

    protected function handleWebRoutes(): void
    {
        $webRoutesPath = base_path('routes/web.php');
        $content = File::get($webRoutesPath);

        // Check if Laravel welcome route exists
        if (str_contains($content, "return view('welcome')")) {
            if ($this->option('force') || confirm('Remove Laravel welcome route from routes/web.php?', default: true)) {
                $newContent = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

// CMS package provides the "/" route via PageController::home
// No application routes needed - CMS handles all frontend routes

PHP;
                File::put($webRoutesPath, $newContent);
                $this->components->success('Removed Laravel welcome route');
            }
        }
    }

    protected function handleDatabaseSeeder(): void
    {
        $seederPath = database_path('seeders/DatabaseSeeder.php');
        $content = File::get($seederPath);

        // Check if CMS seeder is already called
        if (! str_contains($content, 'NetServaCmsSeeder')) {
            if ($this->option('force') || confirm('Add NetServaCmsSeeder to DatabaseSeeder?', default: true)) {
                // Add the call before the closing brace of the run() method
                $pattern = '/(public function run\(\): void\s*\{[^}]*)(}\s*$)/s';
                $replacement = '$1
        // Call CMS package seeder
        $this->call(\NetServa\Cms\Database\Seeders\NetServaCmsSeeder::class);
    $2';

                $newContent = preg_replace($pattern, $replacement, $content);
                File::put($seederPath, $newContent);
                $this->components->success('Added NetServaCmsSeeder to DatabaseSeeder');
            }
        }
    }

    protected function handleFilamentSetup(): void
    {
        $panelProviderPath = app_path('Providers/Filament/AdminPanelProvider.php');

        // Check if Filament panel exists
        if (! File::exists($panelProviderPath)) {
            if ($this->option('force') || confirm('Install Filament admin panel?', default: true)) {
                $this->call('filament:install', ['--panels' => true, '--no-interaction' => true]);
            } else {
                return;
            }
        }

        // Check if CMS resources are discovered
        $content = File::get($panelProviderPath);
        if (! str_contains($content, 'NetServa\Cms\Filament\Resources')) {
            if ($this->option('force') || confirm('Configure admin panel to discover CMS resources?', default: true)) {
                // Add CMS resource discovery after the app resources discovery
                $pattern = '/(->discoverResources\(in: app_path\(\'Filament\/Resources\'\)[^)]*\))/';
                $replacement = '$1
            ->discoverResources(in: base_path(\'vendor/netserva/cms/src/Filament/Resources\'), for: \'NetServa\Cms\Filament\Resources\')';

                $newContent = preg_replace($pattern, $replacement, $content);
                File::put($panelProviderPath, $newContent);

                // Clear caches
                $this->call('optimize:clear');

                $this->components->success('Configured admin panel to discover CMS resources');
            }
        }
    }

    protected function configureFrontendAssets(): void
    {
        $this->components->info('Configuring frontend assets...');

        // Step 1: Update package.json to include typography plugin
        $this->updatePackageJson();

        // Step 2: Create/update app.css with typography plugin
        $this->updateAppCss();

        $this->components->success('Frontend assets configured');
    }

    protected function updatePackageJson(): void
    {
        $packageJsonPath = base_path('package.json');

        if (! File::exists($packageJsonPath)) {
            $this->components->warn('package.json not found - skipping');

            return;
        }

        $packageJson = json_decode(File::get($packageJsonPath), true);

        // Check if typography plugin is already installed
        if (isset($packageJson['devDependencies']['@tailwindcss/typography'])) {
            $this->components->info('@tailwindcss/typography already in package.json');

            return;
        }

        // Add typography plugin to devDependencies
        $packageJson['devDependencies']['@tailwindcss/typography'] = '^0.5.19';

        // Sort devDependencies alphabetically
        ksort($packageJson['devDependencies']);

        // Write back with pretty formatting
        File::put(
            $packageJsonPath,
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->components->success('Added @tailwindcss/typography to package.json');
    }

    protected function updateAppCss(): void
    {
        $appCssPath = resource_path('css/app.css');

        // Create resources/css directory if it doesn't exist
        if (! File::exists(resource_path('css'))) {
            File::makeDirectory(resource_path('css'), 0755, true);
        }

        // Check if app.css exists and already has typography plugin
        if (File::exists($appCssPath)) {
            $content = File::get($appCssPath);
            if (str_contains($content, '@tailwindcss/typography')) {
                $this->components->info('app.css already configured');

                return;
            }
        }

        // Create app.css with full configuration
        $appCssContent = <<<'CSS'
@import 'tailwindcss';
@plugin '@tailwindcss/typography';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

/* Configure dark mode to use class-based switching */
@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
}

/* Fix double backgrounds in code blocks */
.prose pre code {
    background-color: transparent !important;
    padding: 0 !important;
    border-radius: 0 !important;
    color: rgb(243 244 246) !important; /* gray-100 */
}

.dark .prose pre code {
    color: rgb(243 244 246) !important;
}
CSS;

        File::put($appCssPath, $appCssContent);
        $this->components->success('Created app.css with typography plugin');
    }

    protected function createAdminUser(): void
    {
        $name = $this->option('force') ? 'Admin User' : text(
            label: 'Admin name',
            default: 'Admin User',
            required: true
        );

        $email = $this->option('force') ? 'admin@netserva.com' : text(
            label: 'Admin email',
            default: 'admin@netserva.com',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email address'
        );

        $password = $this->option('force') ? 'password' : password(
            label: 'Admin password',
            required: true
        );

        $user = \App\Models\User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $this->components->success("Admin user created: {$user->email}");
    }

    protected function executeShellCommand(string $command): bool
    {
        exec($command.' 2>&1', $output, $exitCode);

        return $exitCode === 0;
    }
}
