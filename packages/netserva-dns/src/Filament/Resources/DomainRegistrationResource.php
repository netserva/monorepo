<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages\ListDomainRegistrations;
use NetServa\Dns\Models\DomainRegistration;
use UnitEnum;

class DomainRegistrationResource extends Resource
{
    protected static ?string $model = DomainRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static UnitEnum|string|null $navigationGroup = 'Dns';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Domains';

    protected static ?string $modelLabel = 'Domain';

    protected static ?string $pluralModelLabel = 'Domains';

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('domain_name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('The domain name to register'),

                Forms\Components\Select::make('domain_registrar_id')
                    ->label('Registrar')
                    ->relationship('domainRegistrar', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm(fn () => DomainRegistrarResource::getFormSchema())
                    ->createOptionAction(fn (Action $action) => $action
                        ->modalHeading('Create Domain Registrar')
                        ->modalWidth(Width::ExtraLarge))
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Domain registrar managing this domain'),
            ]),

            Grid::make(3)->schema([
                Forms\Components\DatePicker::make('registration_date')
                    ->required()
                    ->default(now())
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Date the domain was registered'),

                Forms\Components\DatePicker::make('expiry_date')
                    ->required()
                    ->after('registration_date')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Date the domain registration expires'),

                Forms\Components\DatePicker::make('renewal_date')
                    ->after('registration_date')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Date to renew the domain'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Toggle::make('auto_renew')
                    ->label('Auto-renew')
                    ->default(true)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Automatically renew the domain before expiry'),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'expired' => 'Expired',
                        'transferred' => 'Transferred',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active')
                    ->required()
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Current status of the domain registration'),
            ]),

            Forms\Components\TextInput::make('registrant_contact')
                ->label('Registrant Contact')
                ->maxLength(255)
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Contact information for domain owner'),

            Forms\Components\TagsInput::make('nameservers')
                ->placeholder('e.g., ns1.example.com')
                ->default([])
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Nameservers associated with this domain'),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional notes about this domain registration'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('domainRegistrar.name')
                    ->label('Registrar')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'locked' => 'info',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        'transferred' => 'purple',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('nameservers')
                    ->label('NS')
                    ->getStateUsing(fn ($record) => $record->nameservers[0] ?? '-')
                    ->limit(25)
                    ->tooltip(fn ($record) => implode("\n", $record->nameservers ?? []))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn ($record): string => $record->expiry_date?->isPast() ? 'danger' : ($record->expiry_date?->diffInDays() < 60 ? 'warning' : 'gray')),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('Auto-renew')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'locked' => 'Locked',
                        'pending' => 'Pending',
                        'expired' => 'Expired',
                        'transferred' => 'Transferred',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring in 60 days')
                    ->query(fn ($query) => $query->where('expiry_date', '<=', now()->addDays(60))),
                Tables\Filters\SelectFilter::make('domain_registrar_id')
                    ->label('Registrar')
                    ->relationship('domainRegistrar', 'name'),
                Tables\Filters\TernaryFilter::make('auto_renew')
                    ->label('Auto-renew'),
            ])
            ->searchable(false)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit domain')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete domain'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomainRegistrations::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
