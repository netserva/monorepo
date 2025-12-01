<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\WireguardResource\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use NetServa\Fleet\Filament\Resources\WireguardResource;
use NetServa\Fleet\Models\WireguardPeer;
use NetServa\Fleet\Services\WireguardKeyService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ManagePeers extends ManageRelatedRecords
{
    protected static string $resource = WireguardResource::class;

    protected static string $relationship = 'peers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public function getTitle(): string|Htmlable
    {
        return "Peers: {$this->getOwnerRecord()->name} ({$this->getOwnerRecord()->network_cidr})";
    }

    public static function getNavigationLabel(): string
    {
        return 'Peers';
    }

    public function getBreadcrumbs(): array
    {
        return [
            WireguardResource::getUrl() => 'WireGuard',
            '' => $this->getOwnerRecord()->name,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $server = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('laptop-john')
                ->hintIcon('heroicon-o-question-mark-circle', 'Friendly name to identify this peer (e.g., laptop-john, phone-sarah)')
                ->columnSpan(1),

            TextInput::make('allocated_ip')
                ->required()
                ->label('Allocated IP')
                ->placeholder($server->getNextAvailableIp())
                ->default(fn () => $server->getNextAvailableIp())
                ->rules(['ip'])
                ->hintIcon('heroicon-o-question-mark-circle', 'IP address assigned to this peer within the VPN network')
                ->columnSpan(1),

            TagsInput::make('allowed_ips')
                ->label('Allowed IPs')
                ->placeholder('Add CIDR...')
                ->default(['0.0.0.0/0', '::/0'])
                ->hintIcon('heroicon-o-question-mark-circle', 'Networks this peer can access (0.0.0.0/0 = full tunnel, or specific CIDRs for split tunnel)')
                ->columnSpanFull(),

            TextInput::make('public_key')
                ->label('Public Key')
                ->placeholder('Auto-generated or paste existing')
                ->maxLength(44)
                ->hintIcon('heroicon-o-question-mark-circle', 'Peer\'s Curve25519 public key (auto-generated if left empty)')
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->hintIcon('heroicon-o-question-mark-circle', 'Inactive peers are excluded from server configuration')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('allocated_ip')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->sortable()
                    ->copyable(),

                TextColumn::make('allowed_ips')
                    ->label('Allowed IPs')
                    ->fontFamily('mono')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(),

                TextColumn::make('public_key')
                    ->label('Public Key')
                    ->fontFamily('mono')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->public_key)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_handshake')
                    ->label('Last Seen')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([])
            ->header(null)
            ->recordActions([
                Action::make('config')
                    ->hiddenLabel()
                    ->tooltip('View Config')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('success')
                    ->modalHeading(fn (WireguardPeer $record) => "Config: {$record->name}")
                    ->modalDescription('Copy or download the WireGuard client configuration')
                    ->modalSubmitActionLabel('Download')
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(Width::Large)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalContent(function (WireguardPeer $record): HtmlString {
                        $config = $record->generateClientConfig();
                        $escaped = htmlspecialchars($config);

                        return new HtmlString(
                            "<pre class=\"bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-x-auto font-mono text-sm select-all\">{$escaped}</pre>"
                        );
                    })
                    ->action(function (WireguardPeer $record) {
                        $config = $record->generateClientConfig();
                        $filename = "{$record->name}.conf";

                        return response()->streamDownload(
                            fn () => print ($config),
                            $filename,
                            ['Content-Type' => 'text/plain']
                        );
                    }),

                Action::make('qr')
                    ->hiddenLabel()
                    ->tooltip('Show QR Code')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('info')
                    ->modalHeading(fn (WireguardPeer $record) => "QR Code: {$record->name}")
                    ->modalDescription('Scan with WireGuard mobile app to import configuration')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(Width::Small)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalContent(function (WireguardPeer $record): HtmlString {
                        $config = $record->generateClientConfig();
                        $qr = QrCode::size(280)->margin(1)->generate($config);

                        return new HtmlString(
                            "<div class=\"flex justify-center p-4\">{$qr}</div>"
                        );
                    }),

                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit')
                    ->modalWidth(Width::Large)
                    ->modalFooterActionsAlignment(Alignment::End),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->tooltip('Back to servers')
                ->iconButton()
                ->url(WireguardResource::getUrl()),

            Action::make('serverConfig')
                ->label('Server Config')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->modalHeading(fn () => "Server Config: {$this->getOwnerRecord()->name}")
                ->modalDescription('WireGuard server configuration file')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth(Width::Large)
                ->modalFooterActionsAlignment(Alignment::End)
                ->modalContent(function (): HtmlString {
                    $config = $this->getOwnerRecord()->generateServerConfig();
                    $escaped = htmlspecialchars($config);

                    return new HtmlString(
                        "<pre class=\"bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-x-auto font-mono text-sm\">{$escaped}</pre>"
                    );
                }),

            CreateAction::make()
                ->createAnother(false)
                ->label('New Peer')
                ->icon(Heroicon::Plus)
                ->modalWidth(Width::Large)
                ->modalFooterActionsAlignment(Alignment::End)
                ->mutateFormDataUsing(function (array $data): array {
                    // Generate keypair if not provided
                    if (empty($data['public_key'])) {
                        $keys = WireguardKeyService::generateKeyPair();
                        $data['public_key'] = $keys['public'];
                        $data['private_key_encrypted'] = encrypt($keys['private']);
                    }

                    return $data;
                })
                ->after(function () {
                    Notification::make()
                        ->title('Peer created')
                        ->body('Use the QR code or download the config file to set up the client.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
