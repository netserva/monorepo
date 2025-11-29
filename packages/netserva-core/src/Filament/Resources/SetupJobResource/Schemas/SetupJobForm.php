<?php

namespace NetServa\Core\Filament\Resources\SetupJobResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use NetServa\Core\Filament\Components\SetupFormComponents;

class SetupJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Configuration')
                    ->description('Basic job settings')
                    ->schema([
                        SetupFormComponents::templateSelect(),
                        SetupFormComponents::targetHostSelect(),
                        SetupFormComponents::jobNameInput(),
                        SetupFormComponents::priorityInput(),
                    ])
                    ->columns(2),

                Section::make('Description & Notes')
                    ->schema([
                        SetupFormComponents::descriptionTextarea(),
                    ]),

                Section::make('Configuration Overrides')
                    ->description('Override template default values')
                    ->schema([
                        SetupFormComponents::configurationKeyValue(),
                    ])
                    ->collapsible(),

                Section::make('Execution Options')
                    ->description('Control how this job is executed')
                    ->schema([
                        SetupFormComponents::dryRunToggle(),
                    ]),

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
