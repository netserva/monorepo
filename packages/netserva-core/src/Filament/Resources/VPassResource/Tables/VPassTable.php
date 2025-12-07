<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\VPassResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

class VPassTable
{
    /**
     * Get form schema for create/edit modals
     */
    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('owner_level')
                    ->label('Owner Level')
                    ->required()
                    ->options([
                        'vsite' => 'VSite',
                        'vnode' => 'VNode',
                        'vhost' => 'VHost',
                    ])
                    ->default('vnode')
                    ->live()
                    ->afterStateUpdated(function (callable $set) {
                        $set('fleet_vsite_id', null);
                        $set('fleet_vnode_id', null);
                        $set('fleet_vhost_id', null);
                    })
                    ->dehydrated(false),

                Forms\Components\Select::make('fleet_vsite_id')
                    ->label('VSite')
                    ->options(fn () => FleetVsite::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('owner_level') === 'vsite'),

                Forms\Components\Select::make('fleet_vnode_id')
                    ->label('VNode')
                    ->options(fn () => FleetVnode::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('owner_level') === 'vnode'),

                Forms\Components\Select::make('fleet_vhost_id')
                    ->label('VHost')
                    ->options(fn () => FleetVhost::pluck('domain', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('owner_level') === 'vhost'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('service')
                    ->label('Service')
                    ->required()
                    ->options([
                        VPass::SERVICE_MYSQL => 'MySQL',
                        VPass::SERVICE_SQLITE => 'SQLite',
                        VPass::SERVICE_SSH => 'SSH',
                        VPass::SERVICE_SFTP => 'SFTP',
                        VPass::SERVICE_MAIL => 'Email',
                        VPass::SERVICE_IMAP => 'IMAP',
                        VPass::SERVICE_SMTP => 'SMTP',
                        VPass::SERVICE_WORDPRESS => 'WordPress',
                        VPass::SERVICE_ADMIN => 'Admin Panel',
                        VPass::SERVICE_API => 'API',
                        VPass::SERVICE_CLOUDFLARE => 'Cloudflare',
                        VPass::SERVICE_BINARYLANE => 'BinaryLane',
                    ])
                    ->searchable(),

                Forms\Components\TextInput::make('name')
                    ->label('Name/Identifier')
                    ->required()
                    ->placeholder('e.g., admin, dbuser, api_key')
                    ->helperText('Unique name for this credential'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->placeholder('Optional username/email'),

                Forms\Components\TextInput::make('password')
                    ->label('Secret/Password')
                    ->required()
                    ->password()
                    ->revealable()
                    ->placeholder('Password, API key, or token')
                    ->helperText('Encrypted at rest with APP_KEY'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->placeholder('Optional endpoint URL'),

                Forms\Components\TextInput::make('port')
                    ->label('Port')
                    ->numeric()
                    ->placeholder('Optional port number'),
            ]),

            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->placeholder('Optional admin notes'),
        ];
    }

    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner_display')
                    ->label('Owner')
                    ->getStateUsing(fn (VPass $record) => match (true) {
                        $record->fleet_vhost_id !== null => $record->vhost?->domain ?? 'VHost #'.$record->fleet_vhost_id,
                        $record->fleet_vnode_id !== null => $record->vnode?->name ?? 'VNode #'.$record->fleet_vnode_id,
                        $record->fleet_vsite_id !== null => $record->vsite?->name ?? 'VSite #'.$record->fleet_vsite_id,
                        default => 'Global',
                    }),

                TextColumn::make('service')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (VPass $record) => $record->service_display)
                    ->color(fn (?string $state): string => match ($state) {
                        'cloudflare', 'api' => 'info',
                        'mysql', 'sqlite' => 'warning',
                        'ssh', 'sftp' => 'success',
                        'wordpress', 'admin' => 'primary',
                        'mail', 'imap', 'smtp' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Identifier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label('Username')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('password')
                    ->label('Secret')
                    ->formatStateUsing(fn (?string $state) => $state ? str_repeat('•', min(12, strlen($state))) : '-')
                    ->copyable()
                    ->copyMessage('Secret copied to clipboard'),

                TextColumn::make('url')
                    ->label('URL')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service')
                    ->label('Service')
                    ->options([
                        VPass::SERVICE_MYSQL => 'MySQL',
                        VPass::SERVICE_SSH => 'SSH',
                        VPass::SERVICE_WORDPRESS => 'WordPress',
                        VPass::SERVICE_API => 'API',
                        VPass::SERVICE_CLOUDFLARE => 'Cloudflare',
                        VPass::SERVICE_MAIL => 'Email',
                    ]),
                SelectFilter::make('owner_level')
                    ->label('Owner Level')
                    ->options([
                        'vhost' => 'VHost',
                        'vnode' => 'VNode',
                        'vsite' => 'VSite',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'vhost' => $query->whereNotNull('fleet_vhost_id'),
                            'vnode' => $query->whereNotNull('fleet_vnode_id')->whereNull('fleet_vhost_id'),
                            'vsite' => $query->whereNotNull('fleet_vsite_id')->whereNull('fleet_vnode_id')->whereNull('fleet_vhost_id'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit credential')
                    ->modalWidth(Width::Large)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema())
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Set owner_level based on which FK is set
                        $data['owner_level'] = match (true) {
                            ! empty($data['fleet_vhost_id']) => 'vhost',
                            ! empty($data['fleet_vnode_id']) => 'vnode',
                            ! empty($data['fleet_vsite_id']) => 'vsite',
                            default => 'vnode',
                        };

                        return $data;
                    }),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete credential'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('service')
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }
}
