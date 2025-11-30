<?php

namespace NetServa\Web\Filament\Resources;

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
use NetServa\Web\Filament\Resources\SslCertificateResource\Pages\ListSslCertificates;
use NetServa\Web\Models\SslCertificate;
use UnitEnum;

class SslCertificateResource extends Resource
{
    protected static ?string $model = SslCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 4;

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('common_name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., example.com or *.example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Primary domain name for this certificate. Use *.domain.com for wildcard certificates'),

                Forms\Components\Select::make('ssl_certificate_authority_id')
                    ->label('Certificate Authority')
                    ->required()
                    ->relationship('certificateAuthority', 'name')
                    ->searchable()
                    ->preload()
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('The Certificate Authority that issued this certificate'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('certificate_type')
                    ->required()
                    ->options(SslCertificate::CERTIFICATE_TYPES)
                    ->default('domain')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Type of certificate: single domain, wildcard (*.domain.com), or multi-domain (SAN)'),

                Forms\Components\Select::make('status')
                    ->required()
                    ->options(SslCertificate::STATUSES)
                    ->default('pending')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Current status of the certificate'),
            ]),

            Forms\Components\TagsInput::make('subject_alternative_names')
                ->label('Subject Alternative Names (SANs)')
                ->placeholder('e.g., www.example.com, mail.example.com')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Additional domain names covered by this certificate (for multi-domain certificates)'),

            Grid::make(2)->schema([
                Forms\Components\Select::make('key_type')
                    ->required()
                    ->options(SslCertificate::KEY_TYPES)
                    ->default('rsa')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Type of cryptographic key: RSA (traditional) or ECDSA (modern, smaller)'),

                Forms\Components\TextInput::make('key_size')
                    ->numeric()
                    ->default(2048)
                    ->placeholder('2048')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Key size in bits (2048 or 4096 for RSA, 256 or 384 for ECDSA)'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\DateTimePicker::make('not_valid_before')
                    ->label('Valid From')
                    ->required()
                    ->default(now())
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Start date when certificate becomes valid'),

                Forms\Components\DateTimePicker::make('not_valid_after')
                    ->label('Valid Until')
                    ->required()
                    ->default(now()->addDays(90))
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Expiration date of the certificate'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Toggle::make('auto_renew')
                    ->label('Auto-Renew')
                    ->default(true)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Automatically renew certificate before expiration'),

                Forms\Components\TextInput::make('renew_days_before_expiry')
                    ->label('Renew Days Before Expiry')
                    ->numeric()
                    ->default(30)
                    ->placeholder('30')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Number of days before expiry to trigger automatic renewal'),
            ]),

            Forms\Components\Textarea::make('certificate_pem')
                ->label('Certificate (PEM)')
                ->rows(5)
                ->placeholder('-----BEGIN CERTIFICATE-----')
                ->columnSpanFull()
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('The certificate in PEM format (Base64 encoded)'),

            Forms\Components\Textarea::make('private_key_pem')
                ->label('Private Key (PEM)')
                ->rows(5)
                ->placeholder('-----BEGIN PRIVATE KEY-----')
                ->columnSpanFull()
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('The private key in PEM format (keep this secure!)'),

            Forms\Components\Textarea::make('certificate_chain_pem')
                ->label('Certificate Chain (PEM)')
                ->rows(5)
                ->placeholder('-----BEGIN CERTIFICATE-----')
                ->columnSpanFull()
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Intermediate CA certificates in PEM format (optional)'),

            Forms\Components\Textarea::make('notes')
                ->rows(3)
                ->placeholder('Optional notes about this certificate')
                ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('common_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(fn (SslCertificate $record) => $record->is_wildcard ? 'heroicon-o-star' : null)
                    ->iconPosition('after')
                    ->tooltip(fn (SslCertificate $record) => $record->is_wildcard ? 'Wildcard certificate' : null),

                Tables\Columns\TextColumn::make('certificateAuthority.name')
                    ->label('CA')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('certificate_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'domain' => 'info',
                        'wildcard' => 'success',
                        'multi_domain' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (SslCertificate $record): string => $record->status_color)
                    ->sortable(),

                Tables\Columns\TextColumn::make('not_valid_after')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (SslCertificate $record) => match (true) {
                        $record->isExpired() => 'danger',
                        $record->isExpiringSoon(7) => 'danger',
                        $record->isExpiringSoon(30) => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (SslCertificate $record) => $record->days_until_expiry > 0
                        ? "{$record->days_until_expiry} days remaining"
                        : 'Expired'),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('Auto-Renew')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deployments_count')
                    ->label('Deployments')
                    ->counts('deployments')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SslCertificate::STATUSES),

                Tables\Filters\SelectFilter::make('certificate_type')
                    ->label('Type')
                    ->options(SslCertificate::CERTIFICATE_TYPES),

                Tables\Filters\SelectFilter::make('ssl_certificate_authority_id')
                    ->label('Certificate Authority')
                    ->relationship('certificateAuthority', 'name'),

                Tables\Filters\TernaryFilter::make('auto_renew')
                    ->label('Auto-Renew'),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn ($query) => $query->expiringSoon(30)),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn ($query) => $query->expired()),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit certificate')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete certificate'),
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
            'index' => ListSslCertificates::route('/'),
        ];
    }
}
