<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshKeyResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use NetServa\Core\Models\SshKey;
use NetServa\Core\Services\SshKeySyncService;

class SshKeysTable
{
    /**
     * Get form schema for create/edit modals
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->unique(ignorable: fn ($record) => $record)
                ->maxLength(255)
                ->placeholder('e.g., lan, wan, github')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Key filename without extension'),

            Grid::make(3)
                ->schema([
                    Select::make('type')
                        ->required()
                        ->options([
                            'ed25519' => 'ED25519',
                            'rsa' => 'RSA',
                            'ecdsa' => 'ECDSA',
                        ])
                        ->default('ed25519'),

                    TextInput::make('key_size')
                        ->numeric()
                        ->placeholder('4096')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Only required for RSA keys'),

                    TextInput::make('comment')
                        ->placeholder('user@hostname'),
                ]),

            Textarea::make('public_key')
                ->label('Public Key')
                ->rows(3)
                ->placeholder('ssh-ed25519 AAAAC3NzaC1...')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Contents of .pub file'),

            Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description'),

            Toggle::make('is_active')
                ->label('Disabled')
                ->default(false),
        ];
    }

    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('public_key')
                    ->label('Public Key')
                    ->limit(40)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyableState(fn ($state) => $state)
                    ->copyMessage('Public key copied to clipboard'),

                TextColumn::make('comment')
                    ->limit(30)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'ed25519' => 'success',
                        'rsa' => 'warning',
                        'ecdsa' => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('fingerprint')
                    ->label('Fingerprint')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'ed25519' => 'ED25519',
                        'rsa' => 'RSA',
                        'ecdsa' => 'ECDSA',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::Medium)
                        ->modalFooterActionsAlignment(Alignment::End)
                        ->schema(fn () => self::getFormSchema()),
                    DeleteAction::make(),
                    Action::make('syncToFile')
                        ->label('Sync to File')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->action(function (SshKey $record) {
                            $service = app(SshKeySyncService::class);

                            if ($service->syncKey($record)) {
                                Notification::make()
                                    ->success()
                                    ->title('Key Synced')
                                    ->body("Synced to ~/.ssh/keys/{$record->name}")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Sync Failed')
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                Action::make('generateKeyPair')
                    ->label('Generate Key Pair')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->form([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('mykey')
                            ->helperText('Key name (filename without extension)'),
                        Select::make('type')
                            ->required()
                            ->options([
                                'ed25519' => 'ED25519 (Recommended)',
                                'rsa' => 'RSA 4096',
                                'ecdsa' => 'ECDSA',
                            ])
                            ->default('ed25519'),
                        TextInput::make('comment')
                            ->placeholder('user@hostname')
                            ->helperText('Optional comment for the key'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $service = app(SshKeySyncService::class);
                            $key = $service->generateKeyPair(
                                name: $data['name'],
                                type: $data['type'],
                                comment: $data['comment'] ?? '',
                            );

                            Notification::make()
                                ->success()
                                ->title('Key Pair Generated')
                                ->body("Created {$key->name} ({$key->type})")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Generation Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('importFromFilesystem')
                    ->label('Import from ~/.ssh/keys/')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(function () {
                        $service = app(SshKeySyncService::class);
                        $results = $service->importFromFilesystem();

                        Notification::make()
                            ->success()
                            ->title('Import Complete')
                            ->body("Imported: {$results['imported']}, Skipped: {$results['skipped']}")
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Import SSH Keys')
                    ->modalDescription('Import existing SSH keys from ~/.ssh/keys/ into the database.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('syncSelected')
                        ->label('Sync Selected')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(function (Collection $records) {
                            $service = app(SshKeySyncService::class);
                            $synced = 0;

                            foreach ($records as $record) {
                                if ($service->syncKey($record)) {
                                    $synced++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Sync Complete')
                                ->body("Synced {$synced} keys to filesystem")
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
