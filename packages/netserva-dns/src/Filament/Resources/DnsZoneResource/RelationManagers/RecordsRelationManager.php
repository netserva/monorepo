<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Schemas\DnsRecordForm;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Tables\DnsRecordsTable;

class RecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'records';

    public function form(Schema $schema): Schema
    {
        return DnsRecordForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        // Use the base table configuration but customize for relation context
        $table = DnsRecordsTable::configure($table);

        // Remove the zone column since we're already in the zone context
        $columns = collect($table->getColumns())
            ->filter(fn ($column) => $column->getName() !== 'dnsZone.name')
            ->all();

        return $table
            ->heading('')
            ->columns($columns);
    }
}
