<?php

namespace NetServa\Core\Filament\Resources\MigrationJobResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use NetServa\Core\Filament\Components\MigrationFormComponents;

class MigrationJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                MigrationFormComponents::serverSelectionSection(),

                Section::make('Migration Details')
                    ->description('Domain and job configuration')
                    ->schema([
                        MigrationFormComponents::domainInput(),
                        MigrationFormComponents::jobNameInput(),
                        MigrationFormComponents::descriptionTextarea(),
                    ])
                    ->columns(2),

                MigrationFormComponents::migrationOptionsSection(),

                Section::make('Job Status')
                    ->description('Current execution status (read-only after job starts)')
                    ->schema([
                        TextInput::make('status')
                            ->label('Status')
                            ->default('pending')
                            ->disabled()
                            ->helperText('Job execution status'),

                        TextInput::make('progress')
                            ->label('Progress (%)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->disabled()
                            ->suffix('%')
                            ->helperText('Completion percentage'),

                        DateTimePicker::make('started_at')
                            ->label('Started At')
                            ->disabled(),

                        DateTimePicker::make('completed_at')
                            ->label('Completed At')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
