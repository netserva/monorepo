<?php

namespace NetServa\Core\Filament\Forms\Components;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;

/**
 * NetServa Core JSON Editor Components
 *
 * Standardized JSON editing components for configuration and metadata fields.
 * Part of the NetServa Core foundation package.
 */
class JsonEditor
{
    /**
     * Create a configuration JSON editor
     */
    public static function config(string $name = 'config'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Configuration')
            ->addActionLabel('Add Setting')
            ->keyLabel('Setting')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Add configuration key-value pairs for this resource');
    }

    /**
     * Create a metadata JSON editor
     */
    public static function metadata(string $name = 'metadata'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Metadata')
            ->addActionLabel('Add Metadata')
            ->keyLabel('Key')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Additional metadata for this resource');
    }

    /**
     * Create a custom options JSON editor
     */
    public static function customOptions(string $name = 'custom_options'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Custom Options')
            ->addActionLabel('Add Option')
            ->keyLabel('Option')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Custom configuration options for advanced use cases');
    }

    /**
     * Create an SSH options editor
     */
    public static function sshOptions(string $name = 'ssh_options'): KeyValue
    {
        return KeyValue::make($name)
            ->label('SSH Options')
            ->addActionLabel('Add SSH Option')
            ->keyLabel('Option')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Additional SSH configuration options (e.g., "StrictHostKeyChecking", "ConnectTimeout")');
    }

    /**
     * Create an environment variables editor
     */
    public static function environmentVariables(string $name = 'environment_variables'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Environment Variables')
            ->addActionLabel('Add Variable')
            ->keyLabel('Variable')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Environment variables to set for this resource');
    }

    /**
     * Create a headers editor
     */
    public static function headers(string $name = 'headers'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Headers')
            ->addActionLabel('Add Header')
            ->keyLabel('Header')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('HTTP headers for requests');
    }

    /**
     * Create a properties editor
     */
    public static function properties(string $name = 'properties'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Properties')
            ->addActionLabel('Add Property')
            ->keyLabel('Property')
            ->valueLabel('Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Additional properties for this resource');
    }

    /**
     * Create a raw JSON textarea editor
     */
    public static function rawJson(string $name, ?string $label = null): Textarea
    {
        return Textarea::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->rows(8)
            ->columnSpanFull()
            ->helperText('Enter valid JSON data')
            ->rules(['json'])
            ->placeholder('{}');
    }

    /**
     * Create a DNS records editor (array of records)
     */
    public static function dnsRecords(string $name = 'dns_records'): KeyValue
    {
        return KeyValue::make($name)
            ->label('DNS Records')
            ->addActionLabel('Add DNS Record')
            ->keyLabel('Record Type')
            ->valueLabel('Record Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('DNS records for this domain (e.g., "A", "CNAME", "MX")');
    }

    /**
     * Create a backup sources editor
     */
    public static function backupSources(string $name = 'backup_sources'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Backup Sources')
            ->addActionLabel('Add Source')
            ->keyLabel('Source Type')
            ->valueLabel('Source Path')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Backup source paths and types');
    }

    /**
     * Create a monitoring thresholds editor
     */
    public static function monitoringThresholds(string $name = 'monitoring_thresholds'): KeyValue
    {
        return KeyValue::make($name)
            ->label('Monitoring Thresholds')
            ->addActionLabel('Add Threshold')
            ->keyLabel('Metric')
            ->valueLabel('Threshold Value')
            ->reorderable()
            ->columnSpanFull()
            ->helperText('Monitoring alert thresholds for various metrics');
    }

    /**
     * Create a custom JSON editor with flexible options
     */
    public static function custom(
        string $name,
        ?string $label = null,
        ?string $addActionLabel = null,
        ?string $keyLabel = null,
        ?string $valueLabel = null,
        ?string $helperText = null,
        bool $reorderable = true,
        bool $columnSpanFull = true
    ): KeyValue {
        $component = KeyValue::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->addActionLabel($addActionLabel ?? 'Add Item')
            ->keyLabel($keyLabel ?? 'Key')
            ->valueLabel($valueLabel ?? 'Value')
            ->reorderable($reorderable);

        if ($columnSpanFull) {
            $component->columnSpanFull();
        }

        if ($helperText) {
            $component->helperText($helperText);
        }

        return $component;
    }
}
