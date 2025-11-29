<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Services\RemoteExecutionService;

/**
 * SSH Terminal Page
 *
 * Free-form SSH command execution interface with classic terminal UI.
 * Select a host, enter commands, execute and view output.
 */
class SshTerminal extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'SSH Terminal';

    protected static ?string $title = 'SSH Terminal';

    protected static ?string $slug = 'ssh-terminal';

    protected string $view = 'netserva-core::filament.pages.ssh-terminal';

    public ?string $selectedHost = null;

    public ?string $command = null;

    public bool $bashMode = false;

    public bool $showDebug = false;

    public ?string $lastOutput = null;

    public ?string $lastCommand = null;

    public ?int $lastExitCode = null;

    public ?string $lastHost = null;

    public bool $isRunning = false;

    // Debug timing info
    public ?float $executionTime = null;

    public ?string $connectionInfo = null;

    public function mount(): void
    {
        // Set default host if available
        $defaultHost = SshHost::where('is_active', true)->first();

        if ($defaultHost) {
            $this->selectedHost = $defaultHost->host;
        }

        // Default command
        $this->command = 'hostname && uptime';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        // Left side - Command textarea (no label, 3 rows)
                        Textarea::make('command')
                            ->hiddenLabel()
                            ->required()
                            ->rows(3)
                            ->placeholder('Enter SSH command(s) to execute...')
                            ->extraAttributes([
                                'style' => 'resize: none;',
                            ])
                            ->columnSpan(1),

                        // Right side - Two sub-columns for controls
                        Grid::make(2)
                            ->schema([
                                // Left sub-column: Run button and Debug toggle
                                Group::make([
                                    Actions::make([
                                        Action::make('runCommand')
                                            ->label(fn () => $this->isRunning ? 'Running...' : 'Run Command')
                                            ->icon(fn () => $this->isRunning ? Heroicon::ArrowPath : Heroicon::Play)
                                            ->color(fn () => $this->isRunning ? 'warning' : 'primary')
                                            ->size('lg')
                                            ->disabled(fn () => $this->isRunning)
                                            ->action('executeCommand')
                                            ->button(),
                                    ]),

                                    Toggle::make('showDebug')
                                        ->label('Show Debug')
                                        ->inline(true)
                                        ->live(),
                                ])->columnSpan(1),

                                // Right sub-column: SSH Host selector and Bash Mode toggle
                                Group::make([
                                    Select::make('selectedHost')
                                        ->hiddenLabel()
                                        ->placeholder('Select SSH Host')
                                        ->options(fn () => SshHost::where('is_active', true)
                                            ->pluck('host', 'host')
                                            ->toArray())
                                        ->searchable()
                                        ->live(),

                                    Toggle::make('bashMode')
                                        ->label('Bash Mode')
                                        ->inline(true)
                                        ->helperText('Load .bashrc aliases'),
                                ])->columnSpan(1),
                            ])
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public function executeCommand(): void
    {
        $this->validate([
            'selectedHost' => 'required|string',
            'command' => 'required|string',
        ]);

        $this->isRunning = true;
        $startTime = microtime(true);

        try {
            $service = app(RemoteExecutionService::class);

            // Get host info for debug
            $host = SshHost::where('host', $this->selectedHost)->first();
            $this->connectionInfo = $host
                ? "{$host->user}@{$host->hostname}:{$host->port}"
                : $this->selectedHost;

            // Wrap command based on bash mode
            $commandToExecute = $this->bashMode
                ? 'bash -ci '.escapeshellarg($this->command)
                : $this->command;

            // Execute using the heredoc method (strict mode off for interactive terminal)
            $result = $service->executeScript(
                host: $this->selectedHost,
                script: $commandToExecute,
                args: [],
                asRoot: false,
                strictMode: false,
            );

            $this->lastHost = $this->selectedHost;
            $this->lastCommand = $this->command;
            $this->lastOutput = $result['output'] ?: ($result['error'] ?? 'No output');
            $this->lastExitCode = $result['return_code'] ?? ($result['success'] ? 0 : 1);
            $this->executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($result['success']) {
                Notification::make()
                    ->success()
                    ->title('Command executed successfully')
                    ->body("Exit code: {$this->lastExitCode}")
                    ->send();
            } else {
                Notification::make()
                    ->warning()
                    ->title('Command completed with errors')
                    ->body("Exit code: {$this->lastExitCode}")
                    ->send();
            }

        } catch (\Exception $e) {
            $this->lastHost = $this->selectedHost;
            $this->lastCommand = $this->command;
            $this->lastOutput = $e->getMessage();
            $this->lastExitCode = 255;
            $this->executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Notification::make()
                ->danger()
                ->title('Execution failed')
                ->body($e->getMessage())
                ->send();
        } finally {
            $this->isRunning = false;
        }
    }

    public function clearOutput(): void
    {
        $this->lastOutput = null;
        $this->lastCommand = null;
        $this->lastExitCode = null;
        $this->lastHost = null;
        $this->executionTime = null;
        $this->connectionInfo = null;
    }
}
