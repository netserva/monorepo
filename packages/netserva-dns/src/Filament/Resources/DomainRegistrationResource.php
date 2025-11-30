<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Dns';

    protected static ?int $navigationSort = 30;

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
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('registrar_type')
                            ->options([
                                'cloudflare' => 'Cloudflare',
                                'namecheap' => 'Namecheap',
                                'godaddy' => 'GoDaddy',
                                'gandi' => 'Gandi',
                                'other' => 'Other',
                            ])
                            ->required(),
                    ])
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

            Forms\Components\KeyValue::make('registrant_contact')
                ->label('Registrant Contact')
                ->keyLabel('Field')
                ->valueLabel('Value')
                ->addActionLabel('Add contact field')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Contact information for domain owner'),

            Forms\Components\TagsInput::make('nameservers')
                ->placeholder('e.g., ns1.example.com')
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
                        'pending' => 'warning',
                        'expired' => 'danger',
                        'transferred' => 'info',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

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
                        'pending' => 'Pending',
                        'expired' => 'Expired',
                        'transferred' => 'Transferred',
                        'cancelled' => 'Cancelled',
                    ]),
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
                    ->tooltip('Edit registration')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete registration'),
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
}
