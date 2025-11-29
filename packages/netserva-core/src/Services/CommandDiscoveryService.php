<?php

declare(strict_types=1);

namespace NetServa\Core\Services;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use NetServa\Core\DataObjects\CommandInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Service to discover and parse Artisan commands
 *
 * Provides command information for the Command Runner UI,
 * including argument/option parsing and package detection.
 */
class CommandDiscoveryService
{
    /**
     * Commands that are considered dangerous and require confirmation
     */
    protected array $dangerousCommands = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
        'db:seed',
        'cache:clear',
        'config:clear',
        'route:clear',
        'view:clear',
        'optimize:clear',
        'queue:flush',
        'queue:restart',
    ];

    /**
     * Command prefixes that indicate destructive operations
     */
    protected array $dangerousPrefixes = [
        'del',
    ];

    /**
     * Commands to hide from the UI (internal Laravel commands)
     */
    protected array $hiddenCommands = [
        'help',
        'list',
        'env',
        'completion',
        'about',
        '_complete',
        'clear-compiled',
    ];

    /**
     * Get all available commands as CommandInfo collection
     *
     * @return Collection<int, CommandInfo>
     */
    public function discoverCommands(): Collection
    {
        $artisan = $this->getArtisanApplication();
        $commands = $artisan->all();

        return collect($commands)
            ->map(fn (Command $command) => $this->parseCommand($command))
            ->filter(fn (CommandInfo $info) => ! $info->isHidden)
            ->sortBy('name')
            ->values();
    }

    protected ?ConsoleApplication $artisan = null;

    /**
     * Get the Artisan console application instance
     *
     * Works in both CLI and web contexts by bootstrapping via the Kernel
     */
    protected function getArtisanApplication(): ConsoleApplication
    {
        if ($this->artisan !== null) {
            return $this->artisan;
        }

        $app = App::getInstance();
        $events = $app->make('events');
        $version = $app->version();

        $this->artisan = new ConsoleApplication($app, $events, $version);

        // Resolve all commands from the kernel
        $kernel = $app->make(Kernel::class);

        // Use reflection to get the commands array from the kernel
        $reflection = new \ReflectionClass($kernel);

        // Bootstrap the kernel to load commands
        $kernel->bootstrap();

        // Get commands property
        if ($reflection->hasProperty('commands')) {
            $commandsProperty = $reflection->getProperty('commands');
            $commands = $commandsProperty->getValue($kernel);

            foreach ($commands as $command) {
                $this->artisan->resolve($command);
            }
        }

        // Also resolve commands registered in the container
        $this->artisan->resolveCommands($this->getRegisteredCommands());

        return $this->artisan;
    }

    /**
     * Get commands registered via service providers
     */
    protected function getRegisteredCommands(): array
    {
        $app = App::getInstance();

        // Get deferred commands from the application
        if (method_exists($app, 'getDeferredCommands')) {
            return array_keys($app->getDeferredCommands());
        }

        return [];
    }

    /**
     * Get commands filtered by package
     *
     * @return Collection<int, CommandInfo>
     */
    public function getCommandsByPackage(string $package): Collection
    {
        return $this->discoverCommands()
            ->filter(fn (CommandInfo $info) => $info->package === $package);
    }

    /**
     * Get a specific command by name
     */
    public function getCommand(string $name): ?CommandInfo
    {
        $artisan = $this->getArtisanApplication();

        if (! $artisan->has($name)) {
            return null;
        }

        return $this->parseCommand($artisan->get($name));
    }

    /**
     * Parse a Symfony Console Command into CommandInfo DTO
     */
    protected function parseCommand(Command $command): CommandInfo
    {
        $name = $command->getName();
        $definition = $command->getDefinition();

        return new CommandInfo(
            name: $name,
            description: $command->getDescription(),
            signature: $this->buildSignature($command),
            package: $this->detectPackage($command),
            className: get_class($command),
            arguments: $this->parseArguments($definition->getArguments()),
            options: $this->parseOptions($definition->getOptions()),
            isDangerous: $this->isDangerous($name),
            isHidden: $this->isHidden($command),
        );
    }

    /**
     * Build a human-readable signature from command definition
     */
    protected function buildSignature(Command $command): string
    {
        $parts = [$command->getName()];
        $definition = $command->getDefinition();

        foreach ($definition->getArguments() as $argument) {
            $arg = $argument->getName();
            if ($argument->isRequired()) {
                $parts[] = "<{$arg}>";
            } elseif ($argument->getDefault() !== null && ! is_array($argument->getDefault())) {
                $parts[] = "[{$arg}={$argument->getDefault()}]";
            } else {
                $parts[] = "[{$arg}]";
            }
        }

        foreach ($definition->getOptions() as $option) {
            if ($option->getName() === 'help' || $option->getName() === 'quiet' ||
                $option->getName() === 'verbose' || $option->getName() === 'version' ||
                $option->getName() === 'ansi' || $option->getName() === 'no-ansi' ||
                $option->getName() === 'no-interaction' || $option->getName() === 'env') {
                continue;
            }

            $opt = '--'.$option->getName();
            if ($option->acceptValue()) {
                if ($option->isValueRequired()) {
                    $opt .= '=<value>';
                } else {
                    $opt .= '[=value]';
                }
            }
            $parts[] = "[{$opt}]";
        }

        return implode(' ', $parts);
    }

    /**
     * Parse command arguments into structured array
     *
     * @param  array<string, InputArgument>  $arguments
     * @return array<string, array{name: string, description: string, required: bool, default: mixed}>
     */
    protected function parseArguments(array $arguments): array
    {
        $parsed = [];

        foreach ($arguments as $argument) {
            $parsed[$argument->getName()] = [
                'name' => $argument->getName(),
                'description' => $argument->getDescription(),
                'required' => $argument->isRequired(),
                'default' => $argument->getDefault(),
                'is_array' => $argument->isArray(),
            ];
        }

        return $parsed;
    }

    /**
     * Parse command options into structured array
     *
     * @param  array<string, InputOption>  $options
     * @return array<string, array{name: string, description: string, required: bool, default: mixed, accepts_value: bool, is_array: bool}>
     */
    protected function parseOptions(array $options): array
    {
        $parsed = [];

        // Skip common Laravel/Symfony options
        $skipOptions = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];

        foreach ($options as $option) {
            if (in_array($option->getName(), $skipOptions)) {
                continue;
            }

            $parsed[$option->getName()] = [
                'name' => $option->getName(),
                'description' => $option->getDescription(),
                'required' => $option->isValueRequired(),
                'default' => $option->getDefault(),
                'accepts_value' => $option->acceptValue(),
                'is_array' => $option->isArray(),
                'shortcut' => $option->getShortcut(),
            ];
        }

        return $parsed;
    }

    /**
     * Detect which package a command belongs to
     */
    protected function detectPackage(Command $command): string
    {
        $class = get_class($command);
        $name = $command->getName();

        // Check namespace first
        if (str_contains($class, 'NetServa\\Core\\')) {
            return 'Core';
        }
        if (str_contains($class, 'NetServa\\Fleet\\')) {
            return 'Fleet';
        }
        if (str_contains($class, 'NetServa\\Dns\\')) {
            return 'Dns';
        }
        if (str_contains($class, 'NetServa\\Mail\\')) {
            return 'Mail';
        }
        if (str_contains($class, 'NetServa\\Web\\')) {
            return 'Web';
        }
        if (str_contains($class, 'NetServa\\Ops\\')) {
            return 'Ops';
        }
        if (str_contains($class, 'NetServa\\Config\\')) {
            return 'Config';
        }
        if (str_contains($class, 'NetServa\\Cms\\')) {
            return 'Cms';
        }

        // Check command name prefix
        if (str_starts_with($name, 'fleet:')) {
            return 'Fleet';
        }
        if (str_starts_with($name, 'dns:')) {
            return 'Dns';
        }
        if (str_starts_with($name, 'mail:')) {
            return 'Mail';
        }
        if (str_starts_with($name, 'web:')) {
            return 'Web';
        }
        if (str_starts_with($name, 'ops:')) {
            return 'Ops';
        }
        if (str_starts_with($name, 'config:')) {
            return 'Config';
        }
        if (str_starts_with($name, 'cms:')) {
            return 'Cms';
        }

        // NetServa CRUD commands (add*, sh*, ch*, del*)
        $crudPrefixes = ['addssh', 'shssh', 'chssh', 'delssh', 'addcfg', 'shcfg', 'chcfg', 'delcfg',
            'addpw', 'shpw', 'chpw', 'delpw', 'ns:', 'remote:', 'tunnel', 'use:', 'validate'];
        foreach ($crudPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return 'Core';
            }
        }

        // Laravel framework commands
        if (str_contains($class, 'Illuminate\\') || str_contains($class, 'Laravel\\')) {
            return 'Laravel';
        }

        // Filament commands
        if (str_contains($class, 'Filament\\')) {
            return 'Filament';
        }

        return 'Other';
    }

    /**
     * Check if a command is considered dangerous
     */
    protected function isDangerous(string $name): bool
    {
        if (in_array($name, $this->dangerousCommands)) {
            return true;
        }

        foreach ($this->dangerousPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a command should be hidden from the UI
     */
    protected function isHidden(Command $command): bool
    {
        if ($command->isHidden()) {
            return true;
        }

        return in_array($command->getName(), $this->hiddenCommands);
    }

    /**
     * Get unique packages from all commands
     *
     * @return Collection<int, string>
     */
    public function getPackages(): Collection
    {
        return $this->discoverCommands()
            ->pluck('package')
            ->unique()
            ->sort()
            ->values();
    }
}
