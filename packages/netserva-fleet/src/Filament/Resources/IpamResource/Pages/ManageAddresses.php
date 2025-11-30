<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\IpamResource\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use NetServa\Fleet\Filament\Resources\IpamResource;
use NetServa\Fleet\Models\IpAddress;
use NetServa\Fleet\Services\IpamDiscoveryService;

class ManageAddresses extends ManageRelatedRecords
{
    protected static string $resource = IpamResource::class;

    protected static string $relationship = 'ipAddresses';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    public function getTitle(): string|Htmlable
    {
        return "{$this->getOwnerRecord()->name} ({$this->getOwnerRecord()->cidr})";
    }

    public static function getNavigationLabel(): string
    {
        return 'IP Addresses';
    }

    public function getBreadcrumbs(): array
    {
        return [
            IpamResource::getUrl() => 'Networks',
            '' => $this->getOwnerRecord()->name,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('ip_address')
                ->required()
                ->label('IP Address')
                ->placeholder('192.168.1.100')
                ->rules([
                    fn () => function (string $attribute, $value, $fail) {
                        if (! filter_var($value, FILTER_VALIDATE_IP)) {
                            $fail('Invalid IP address format.');

                            return;
                        }
                        $network = $this->getOwnerRecord();
                        if (! $network->containsIp($value)) {
                            $fail("IP must be within {$network->cidr}");
                        }
                    },
                ])
                ->columnSpan(1),

            TextInput::make('hostname')
                ->placeholder('server1')
                ->maxLength(255)
                ->columnSpan(1),

            Select::make('status')
                ->options(IpAddress::STATUSES)
                ->default('allocated')
                ->required()
                ->columnSpan(1),

            TextInput::make('owner')
                ->placeholder('admin')
                ->maxLength(255)
                ->columnSpan(1),

            TextInput::make('service')
                ->placeholder('Web Server')
                ->maxLength(255)
                ->columnSpan(1),

            TextInput::make('mac_address')
                ->label('MAC Address')
                ->placeholder('00:00:00:00:00:00')
                ->maxLength(17)
                ->columnSpan(1),

            TextInput::make('description')
                ->placeholder('Notes about this address')
                ->maxLength(255)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->sortable()
                    ->searchable()
                    ->copyable(),

                TextColumn::make('hostname')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'allocated' => 'info',
                        'reserved' => 'warning',
                        'discovered' => 'purple',
                        'gateway' => 'primary',
                        'dns', 'ntp' => 'cyan',
                        'blacklisted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('owner')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('service')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('mac_address')
                    ->label('MAC')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                IconColumn::make('is_pingable')
                    ->label('Ping')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(IpAddress::STATUSES)
                    ->multiple(),
            ])
            ->headerActions([])
            ->header(null)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit')
                    ->modalWidth(Width::Large)
                    ->modalFooterActionsAlignment(Alignment::End),

                Action::make('ping')
                    ->hiddenLabel()
                    ->tooltip('Ping')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('info')
                    ->action(function (IpAddress $record) {
                        $result = null;
                        $cmd = sprintf('ping -c 1 -W 1 %s >/dev/null 2>&1', escapeshellarg($record->ip_address));
                        system($cmd, $result);

                        $record->update([
                            'last_ping_at' => now(),
                            'is_pingable' => $result === 0,
                            'ping_count' => $record->ping_count + 1,
                            'last_seen_at' => $result === 0 ? now() : $record->last_seen_at,
                        ]);

                        Notification::make()
                            ->title($result === 0 ? 'Host is alive' : 'Host unreachable')
                            ->icon($result === 0 ? Heroicon::Check : Heroicon::XMark)
                            ->color($result === 0 ? 'success' : 'danger')
                            ->send();
                    }),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->tooltip('Back to networks')
                ->iconButton()
                ->url(IpamResource::getUrl()),

            CreateAction::make()
                ->label('New IP Address')
                ->icon(Heroicon::Plus)
                ->modalWidth(Width::Large)
                ->modalFooterActionsAlignment(Alignment::End)
                ->mutateFormDataUsing(function (array $data): array {
                    $data['allocated_at'] = now();

                    return $data;
                }),

            Action::make('scan')
                ->label('Scan Network')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(fn () => "Network Scan: {$this->getOwnerRecord()->name}")
                ->modalDescription(fn () => "Ping sweep {$this->getOwnerRecord()->cidr} to discover live hosts. This may take a few seconds.")
                ->modalSubmitActionLabel('Start Scan')
                ->modalWidth(Width::Small)
                ->modalFooterActionsAlignment(Alignment::End)
                ->action(function () {
                    $record = $this->getOwnerRecord();
                    $service = app(IpamDiscoveryService::class);
                    $stats = $service->scanNetwork($record);

                    if ($stats['hosts_alive'] > 0) {
                        Notification::make()
                            ->title('Scan Complete')
                            ->body(sprintf(
                                'Found %d live hosts (%d new, %d updated) out of %d scanned',
                                $stats['hosts_alive'],
                                $stats['addresses_created'],
                                $stats['addresses_updated'],
                                $stats['hosts_scanned']
                            ))
                            ->success()
                            ->send();

                        $this->redirect(IpamResource::getUrl('addresses', ['record' => $record->id]));
                    } else {
                        Notification::make()
                            ->title('No Hosts Found')
                            ->body(sprintf(
                                'No live hosts found in %s (scanned %d addresses)',
                                $record->cidr,
                                $stats['hosts_scanned']
                            ))
                            ->info()
                            ->send();
                    }
                }),
        ];
    }
}
