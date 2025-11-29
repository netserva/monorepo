<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Pages;

use BackedEnum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;
use NetServa\Core\DataObjects\CommandInfo;
use NetServa\Core\Services\CommandDiscoveryService;

/**
 * Command Runner Page
 *
 * Universal artisan command execution interface.
 * Lists all available commands with dynamic form generation for execution.
 */
class CommandRunner extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Commands';

    protected static ?string $title = 'Command Runner';

    protected static ?string $slug = 'commands';

    protected string $view = 'netserva-core::filament.pages.command-runner';

    public ?string $lastOutput = null;

    public ?string $lastCommand = null;

    public ?int $lastExitCode = null;

    public function table(Table $table): Table
    {
        return $table
            ->records(function () {
                $service = app(CommandDiscoveryService::class);
                $commands = $service->discoverCommands();

                return $commands->map(fn (CommandInfo $cmd) => $cmd->toArray())->toArray();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Command')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn (array $record) => $record['is_dangerous'] ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('danger')
                    ->description(fn (array $record) => $record['description']),
                TextColumn::make('package')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Core' => 'primary',
                        'Fleet' => 'success',
                        'Dns' => 'info',
                        'Mail' => 'warning',
                        'Web' => 'gray',
                        'Ops' => 'danger',
                        'Config' => 'purple',
                        'Cms' => 'pink',
                        'Laravel' => 'gray',
                        'Filament' => 'orange',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (array $record) => $record['badge_color'])
                    ->sortable(),
                TextColumn::make('signature')
                    ->label('Usage')
                    ->fontFamily('mono')
                    ->size('sm')
                    ->color('gray')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('package')
                    ->options(function () {
                        $service = app(CommandDiscoveryService::class);

                        return $service->getPackages()->mapWithKeys(fn ($p) => [$p => $p])->toArray();
                    }),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('execute')
                    ->label('Run')
                    ->icon(Heroicon::Play)
                    ->color(fn (array $record) => $record['is_dangerous'] ? 'danger' : 'primary')
                    ->modalHeading(fn (array $record) => "Execute: {$record['name']}")
                    ->modalDescription(fn (array $record) => $record['description'])
                    ->modalWidth(Width::ExtraLarge)
                    ->modalIcon(fn (array $record) => $record['is_dangerous'] ? Heroicon::ExclamationTriangle : Heroicon::CommandLine)
                    ->modalIconColor(fn (array $record) => $record['is_dangerous'] ? 'danger' : 'primary')
                    ->schema(fn (array $record) => $this->buildCommandForm($record))
                    ->action(function (array $record, array $data) {
                        $this->executeCommand($record, $data);
                    })
                    ->requiresConfirmation(fn (array $record) => $record['is_dangerous'])
                    ->modalSubmitActionLabel('Execute'),
                \Filament\Actions\Action::make('help')
                    ->label('')
                    ->icon(Heroicon::QuestionMarkCircle)
                    ->color('gray')
                    ->modalHeading(fn (array $record) => "Help: {$record['name']}")
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (array $record) => [
                        Placeholder::make('signature')
                            ->label('Signature')
                            ->content(fn () => new HtmlString("<code class=\"text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded\">{$record['signature']}</code>")),
                        Placeholder::make('description')
                            ->label('Description')
                            ->content($record['description']),
                        Placeholder::make('class')
                            ->label('Class')
                            ->content(fn () => new HtmlString("<code class=\"text-xs text-gray-500\">{$record['class_name']}</code>")),
                    ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50);
    }

    /**
     * Build dynamic form fields based on command arguments and options
     */
    protected function buildCommandForm(array $record): array
    {
        $fields = [];

        // Show signature at top
        $fields[] = Placeholder::make('usage')
            ->label('Usage')
            ->content(fn () => new HtmlString("<code class=\"text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded font-mono\">{$record['signature']}</code>"));

        // Build argument fields
        foreach ($record['arguments'] as $name => $arg) {
            $field = TextInput::make("arg_{$name}")
                ->label($name)
                ->helperText($arg['description'] ?: null)
                ->required($arg['required'])
                ->default($arg['default']);

            if ($arg['is_array'] ?? false) {
                $field->helperText(($arg['description'] ? $arg['description'].' ' : '').'(Comma-separated for multiple values)');
            }

            $fields[] = $field;
        }

        // Build option fields
        foreach ($record['options'] as $name => $opt) {
            if ($opt['accepts_value']) {
                // Option with value
                $field = TextInput::make("opt_{$name}")
                    ->label("--{$name}")
                    ->helperText($opt['description'] ?: null)
                    ->default($opt['default']);

                if ($opt['is_array'] ?? false) {
                    $field->helperText(($opt['description'] ? $opt['description'].' ' : '').'(Comma-separated for multiple values)');
                }
            } else {
                // Boolean flag option
                $field = Checkbox::make("opt_{$name}")
                    ->label("--{$name}")
                    ->helperText($opt['description'] ?: null)
                    ->default((bool) $opt['default']);
            }

            $fields[] = $field;
        }

        // If no parameters, show a message
        if (empty($record['arguments']) && empty($record['options'])) {
            $fields[] = Placeholder::make('no_params')
                ->label('')
                ->content('This command has no additional parameters.');
        }

        return $fields;
    }

    /**
     * Execute the artisan command with provided data
     */
    protected function executeCommand(array $record, array $data): void
    {
        $commandName = $record['name'];
        $arguments = [];

        // Process arguments
        foreach ($record['arguments'] as $name => $arg) {
            $value = $data["arg_{$name}"] ?? null;
            if ($value !== null && $value !== '') {
                // Handle array arguments
                if ($arg['is_array'] ?? false) {
                    $arguments[$name] = array_map('trim', explode(',', $value));
                } else {
                    $arguments[$name] = $value;
                }
            }
        }

        // Process options
        foreach ($record['options'] as $name => $opt) {
            $value = $data["opt_{$name}"] ?? null;

            if ($opt['accepts_value']) {
                if ($value !== null && $value !== '') {
                    if ($opt['is_array'] ?? false) {
                        $arguments["--{$name}"] = array_map('trim', explode(',', $value));
                    } else {
                        $arguments["--{$name}"] = $value;
                    }
                }
            } else {
                // Boolean flag
                if ($value) {
                    $arguments["--{$name}"] = true;
                }
            }
        }

        // Execute command
        try {
            $exitCode = Artisan::call($commandName, $arguments);
            $output = Artisan::output();

            $this->lastCommand = $commandName.' '.collect($arguments)
                ->map(function ($value, $key) {
                    if (is_bool($value)) {
                        return $value ? $key : '';
                    }
                    if (is_array($value)) {
                        return "{$key}=".implode(',', $value);
                    }

                    return "{$key}={$value}";
                })
                ->filter()
                ->implode(' ');
            $this->lastOutput = $output;
            $this->lastExitCode = $exitCode;

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Command executed successfully')
                    ->body("Exit code: {$exitCode}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Command completed with errors')
                    ->body("Exit code: {$exitCode}")
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            $this->lastCommand = $commandName;
            $this->lastOutput = $e->getMessage();
            $this->lastExitCode = 1;

            Notification::make()
                ->title('Command execution failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearOutput(): void
    {
        $this->lastOutput = null;
        $this->lastCommand = null;
        $this->lastExitCode = null;
    }
}
