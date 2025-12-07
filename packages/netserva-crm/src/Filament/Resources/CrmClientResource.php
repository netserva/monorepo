<?php

declare(strict_types=1);

namespace NetServa\Crm\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use NetServa\Crm\CrmServiceProvider;
use NetServa\Crm\Filament\Resources\CrmClientResource\Pages;
use NetServa\Crm\Filament\Resources\CrmClientResource\RelationManagers;
use NetServa\Crm\Models\CrmClient;

class CrmClientResource extends Resource
{
    protected static ?string $model = CrmClient::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $modelLabel = 'Client';

    protected static ?string $pluralModelLabel = 'Clients';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client Details')
                    ->schema([
                        // Personal details
                        TextInput::make('first_name')
                            ->label('First Name')
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->maxLength(255),

                        // Business details (optional)
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->helperText('Optional - for business clients')
                            ->maxLength(255),

                        TextInput::make('abn')
                            ->label('ABN')
                            ->placeholder('XX XXX XXX XXX')
                            ->maxLength(14),

                        TextInput::make('acn')
                            ->label('ACN')
                            ->placeholder('XXX XXX XXX')
                            ->maxLength(11),

                        // Contact
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('home_phone')
                            ->label('Home Phone')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('work_phone')
                            ->label('Work Phone')
                            ->tel()
                            ->maxLength(20),

                        // Status in main section
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'prospect' => 'Prospect',
                                'suspended' => 'Suspended',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Address')
                    ->schema([
                        TextInput::make('address_line_1')
                            ->label('Street Address')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('address_line_2')
                            ->label('Address Line 2')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('city')
                            ->maxLength(255),

                        TextInput::make('state')
                            ->label('State/Territory')
                            ->maxLength(50),

                        TextInput::make('postcode')
                            ->maxLength(20),

                        Select::make('country')
                            ->options([
                                'AU' => 'Australia',
                                'NZ' => 'New Zealand',
                                'US' => 'United States',
                                'GB' => 'United Kingdom',
                                'CA' => 'Canada',
                            ])
                            ->default('AU')
                            ->searchable(),
                    ])
                    ->columns(4)
                    ->collapsible(),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('external_id')
                            ->label('External System ID')
                            ->helperText('Link to WHMCS or other external system')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),

            TextColumn::make('company_name')
                ->label('Company')
                ->searchable()
                ->toggleable(),

            TextColumn::make('email')
                ->searchable()
                ->copyable()
                ->copyMessage('Email copied'),

            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'prospect' => 'warning',
                    'suspended' => 'danger',
                    'cancelled' => 'gray',
                    default => 'gray',
                }),
        ];

        // Add VSites count if Fleet integration available
        if (CrmServiceProvider::hasFleetIntegration()) {
            $columns[] = TextColumn::make('vsites_count')
                ->label('VSites')
                ->counts('vsites')
                ->sortable();
        }

        // Add Domains count if Domain integration available
        if (CrmServiceProvider::hasDomainIntegration()) {
            $columns[] = TextColumn::make('domains_count')
                ->label('Domains')
                ->counts('domains')
                ->sortable();
        }

        $columns[] = TextColumn::make('created_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        return $table
            ->columns($columns)
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'prospect' => 'Prospect',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('client_type')
                    ->label('Type')
                    ->options([
                        'business' => 'Business',
                        'personal' => 'Personal',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'business') {
                            $query->whereNotNull('company_name');
                        } elseif ($data['value'] === 'personal') {
                            $query->whereNull('company_name');
                        }
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        $relations = [];

        // Conditionally add relation managers based on available integrations
        if (CrmServiceProvider::hasFleetIntegration()) {
            $relations[] = RelationManagers\VsitesRelationManager::class;
        }

        if (CrmServiceProvider::hasDomainIntegration()) {
            $relations[] = RelationManagers\DomainsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCrmClients::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'company_name', 'first_name', 'last_name', 'abn'];
    }
}
