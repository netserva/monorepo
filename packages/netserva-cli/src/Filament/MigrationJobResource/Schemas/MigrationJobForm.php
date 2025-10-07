<?php

namespace NetServa\Cli\Filament\Resources\MigrationJobResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MigrationJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source_server')
                    ->required(),
                TextInput::make('target_server')
                    ->required(),
                TextInput::make('domain')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('progress')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('completed_at'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                TextInput::make('migration_type')
                    ->required()
                    ->default('full'),
                Textarea::make('configuration')
                    ->columnSpanFull(),
                Select::make('ssh_host_id')
                    ->relationship('sshHost', 'id'),
                TextInput::make('job_name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('dry_run')
                    ->required(),
                Toggle::make('step_backup')
                    ->required(),
                Toggle::make('step_cleanup')
                    ->required(),
            ]);
    }
}
