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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use NetServa\Fleet\Filament\Resources\IpamResource;

class ManageReservations extends ManageRelatedRecords
{
    protected static string $resource = IpamResource::class;

    protected static string $relationship = 'ipReservations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    public function getTitle(): string|Htmlable
    {
        return "Reservations: {$this->getOwnerRecord()->name} ({$this->getOwnerRecord()->cidr})";
    }

    public static function getNavigationLabel(): string
    {
        return 'Reservations';
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
        $network = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('DHCP Pool')
                ->columnSpanFull(),

            TextInput::make('start_ip')
                ->required()
                ->label('Start IP')
                ->placeholder($network->ip_version === '4' ? '192.168.1.100' : '2001:db8::100')
                ->rules([
                    fn () => function (string $attribute, $value, $fail) use ($network) {
                        if (! filter_var($value, FILTER_VALIDATE_IP)) {
                            $fail('Invalid IP address format.');

                            return;
                        }
                        if (! $network->containsIp($value)) {
                            $fail("IP must be within {$network->cidr}");
                        }
                    },
                ])
                ->columnSpan(1),

            TextInput::make('end_ip')
                ->required()
                ->label('End IP')
                ->placeholder($network->ip_version === '4' ? '192.168.1.200' : '2001:db8::200')
                ->rules([
                    fn () => function (string $attribute, $value, $fail) use ($network) {
                        if (! filter_var($value, FILTER_VALIDATE_IP)) {
                            $fail('Invalid IP address format.');

                            return;
                        }
                        if (! $network->containsIp($value)) {
                            $fail("IP must be within {$network->cidr}");
                        }
                    },
                ])
                ->columnSpan(1),

            Textarea::make('description')
                ->placeholder('Notes about this reservation')
                ->rows(2)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('start_ip')
                    ->label('Start')
                    ->fontFamily('mono')
                    ->sortable(),

                TextColumn::make('end_ip')
                    ->label('End')
                    ->fontFamily('mono')
                    ->sortable(),

                TextColumn::make('address_count')
                    ->label('Count')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->header(null)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit')
                    ->modalWidth(Width::Medium)
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
            ->defaultSort('start_ip')
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
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
                ->createAnother(false)
                ->label('New Reservation')
                ->icon(Heroicon::Plus)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End),
        ];
    }
}
