<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshKeyResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Core\Filament\Resources\SshKeyResource;
use NetServa\Core\Filament\Resources\SshKeyResource\Tables\SshKeysTable;
use NetServa\Core\Services\SshKeySyncService;

class ListSshKeys extends ListRecords
{
    protected static string $resource = SshKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => SshKeysTable::getFormSchema()),
            Action::make('generateKeyPair')
                ->label('Generate Key Pair')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('mykey')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Key name (filename without extension)'),
                    Forms\Components\Select::make('type')
                        ->required()
                        ->options([
                            'ed25519' => 'ED25519 (Recommended)',
                            'rsa' => 'RSA 4096',
                            'ecdsa' => 'ECDSA',
                        ])
                        ->default('ed25519'),
                    Forms\Components\TextInput::make('comment')
                        ->placeholder('user@hostname')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Optional comment for the key'),
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
        ];
    }
}
