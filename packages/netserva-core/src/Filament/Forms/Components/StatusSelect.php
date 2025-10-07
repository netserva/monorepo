<?php

namespace NetServa\Core\Filament\Forms\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

/**
 * NetServa Core Status Select Components
 *
 * Standardized status selection components with consistent options and labeling.
 * Part of the NetServa Core foundation package.
 */
class StatusSelect
{
    /**
     * Create a standardized active/inactive toggle
     */
    public static function activeToggle(string $name = 'is_active'): Toggle
    {
        return Toggle::make($name)
            ->label('Active')
            ->helperText('Enable or disable this resource')
            ->default(true)
            ->inline(false);
    }

    /**
     * Create an enabled/disabled toggle
     */
    public static function enabledToggle(string $name = 'enabled'): Toggle
    {
        return Toggle::make($name)
            ->label('Enabled')
            ->helperText('Enable or disable this feature')
            ->default(true)
            ->inline(false);
    }

    /**
     * Create a status select dropdown
     */
    public static function statusSelect(string $name = 'status'): Select
    {
        return Select::make($name)
            ->label('Status')
            ->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
                'pending' => 'Pending',
                'failed' => 'Failed',
                'suspended' => 'Suspended',
            ])
            ->default('active')
            ->required()
            ->native(false);
    }

    /**
     * Create a connection status select
     */
    public static function connectionStatus(string $name = 'connection_status'): Select
    {
        return Select::make($name)
            ->label('Connection Status')
            ->options([
                'connected' => 'Connected',
                'disconnected' => 'Disconnected',
                'connecting' => 'Connecting',
                'failed' => 'Failed',
                'timeout' => 'Timeout',
            ])
            ->default('disconnected')
            ->native(false);
    }

    /**
     * Create a priority select
     */
    public static function priority(string $name = 'priority'): Select
    {
        return Select::make($name)
            ->label('Priority')
            ->options([
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'critical' => 'Critical',
            ])
            ->default('normal')
            ->required()
            ->native(false);
    }

    /**
     * Create a severity level select
     */
    public static function severity(string $name = 'severity_level'): Select
    {
        return Select::make($name)
            ->label('Severity Level')
            ->options([
                'low' => 'Low',
                'medium' => 'Medium',
                'high' => 'High',
                'critical' => 'Critical',
            ])
            ->default('low')
            ->required()
            ->native(false);
    }

    /**
     * Create a service status select
     */
    public static function serviceStatus(string $name = 'service_status'): Select
    {
        return Select::make($name)
            ->label('Service Status')
            ->options([
                'running' => 'Running',
                'stopped' => 'Stopped',
                'starting' => 'Starting',
                'stopping' => 'Stopping',
                'failed' => 'Failed',
                'unknown' => 'Unknown',
            ])
            ->default('stopped')
            ->native(false);
    }

    /**
     * Create a deployment status select
     */
    public static function deploymentStatus(string $name = 'deployment_status'): Select
    {
        return Select::make($name)
            ->label('Deployment Status')
            ->options([
                'pending' => 'Pending',
                'deploying' => 'Deploying',
                'deployed' => 'Deployed',
                'failed' => 'Failed',
                'rolled_back' => 'Rolled Back',
            ])
            ->default('pending')
            ->native(false);
    }

    /**
     * Create a health status select
     */
    public static function healthStatus(string $name = 'health_status'): Select
    {
        return Select::make($name)
            ->label('Health Status')
            ->options([
                'healthy' => 'Healthy',
                'warning' => 'Warning',
                'critical' => 'Critical',
                'unknown' => 'Unknown',
            ])
            ->default('unknown')
            ->native(false);
    }

    /**
     * Create a verification status select
     */
    public static function verificationStatus(string $name = 'verification_status'): Select
    {
        return Select::make($name)
            ->label('Verification Status')
            ->options([
                'pending' => 'Pending',
                'verified' => 'Verified',
                'failed' => 'Failed',
                'expired' => 'Expired',
            ])
            ->default('pending')
            ->native(false);
    }

    /**
     * Create a job status select
     */
    public static function jobStatus(string $name = 'job_status'): Select
    {
        return Select::make($name)
            ->label('Job Status')
            ->options([
                'queued' => 'Queued',
                'running' => 'Running',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
            ])
            ->default('queued')
            ->native(false);
    }

    /**
     * Create a custom status select with provided options
     */
    public static function custom(
        string $name,
        array $options,
        ?string $label = null,
        ?string $default = null,
        bool $required = false,
        bool $native = false
    ): Select {
        return Select::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->options($options)
            ->default($default)
            ->required($required)
            ->native($native);
    }
}
