<?php

namespace NetServa\Core\Filament\Forms\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * NetServa Core Common Fields
 *
 * Standardized form fields that are commonly used across NetServa packages.
 * Part of the NetServa Core foundation package.
 */
class CommonFields
{
    /**
     * Create a standardized name field
     */
    public static function name(string $name = 'name'): TextInput
    {
        return TextInput::make($name)
            ->label('Name')
            ->required()
            ->maxLength(255)
            ->placeholder('Enter a descriptive name')
            ->columnSpanFull();
    }

    /**
     * Create a standardized description field
     */
    public static function description(string $name = 'description'): Textarea
    {
        return Textarea::make($name)
            ->label('Description')
            ->rows(3)
            ->maxLength(1000)
            ->placeholder('Optional description for this resource')
            ->columnSpanFull();
    }

    /**
     * Create a hostname field with validation
     */
    public static function hostname(string $name = 'hostname'): TextInput
    {
        return TextInput::make($name)
            ->label('Hostname')
            ->required()
            ->maxLength(255)
            ->placeholder('example.com or 192.168.1.100')
            ->rules(['regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$|^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/']);
    }

    /**
     * Create a port field with validation
     */
    public static function port(string $name = 'port', int $default = 22): TextInput
    {
        return TextInput::make($name)
            ->label('Port')
            ->required()
            ->numeric()
            ->default($default)
            ->minValue(1)
            ->maxValue(65535)
            ->placeholder((string) $default);
    }

    /**
     * Create a username field
     */
    public static function username(string $name = 'user'): TextInput
    {
        return TextInput::make($name)
            ->label('Username')
            ->required()
            ->maxLength(255)
            ->default('root')
            ->placeholder('root');
    }

    /**
     * Create an email field with validation
     */
    public static function email(string $name = 'email'): TextInput
    {
        return TextInput::make($name)
            ->label('Email Address')
            ->email()
            ->required()
            ->maxLength(255)
            ->placeholder('user@example.com');
    }

    /**
     * Create a URL field with validation
     */
    public static function url(string $name = 'url'): TextInput
    {
        return TextInput::make($name)
            ->label('URL')
            ->url()
            ->required()
            ->maxLength(500)
            ->placeholder('https://example.com');
    }

    /**
     * Create an IP address field
     */
    public static function ipAddress(string $name = 'ip_address'): TextInput
    {
        return TextInput::make($name)
            ->label('IP Address')
            ->required()
            ->maxLength(45) // IPv6 can be up to 45 characters
            ->placeholder('192.168.1.100 or 2001:db8::1')
            ->rules(['ip']);
    }

    /**
     * Create a file path field
     */
    public static function filePath(string $name = 'file_path', ?string $placeholder = null): TextInput
    {
        return TextInput::make($name)
            ->label('File Path')
            ->maxLength(1000)
            ->placeholder($placeholder ?? '/path/to/file')
            ->helperText('Full path to the file on the filesystem');
    }

    /**
     * Create a directory path field
     */
    public static function directoryPath(string $name = 'directory_path', ?string $placeholder = null): TextInput
    {
        return TextInput::make($name)
            ->label('Directory Path')
            ->maxLength(1000)
            ->placeholder($placeholder ?? '/path/to/directory')
            ->helperText('Full path to the directory on the filesystem');
    }

    /**
     * Create a command field
     */
    public static function command(string $name = 'command'): TextInput
    {
        return TextInput::make($name)
            ->label('Command')
            ->required()
            ->maxLength(1000)
            ->placeholder('echo "Hello World"')
            ->columnSpanFull();
    }

    /**
     * Create a timeout field (in seconds)
     */
    public static function timeout(string $name = 'timeout', int $default = 30): TextInput
    {
        return TextInput::make($name)
            ->label('Timeout (seconds)')
            ->numeric()
            ->default($default)
            ->minValue(1)
            ->maxValue(3600)
            ->placeholder((string) $default)
            ->helperText('Maximum time to wait in seconds');
    }

    /**
     * Create a retry count field
     */
    public static function retryCount(string $name = 'retry_count', int $default = 3): TextInput
    {
        return TextInput::make($name)
            ->label('Retry Count')
            ->numeric()
            ->default($default)
            ->minValue(0)
            ->maxValue(10)
            ->placeholder((string) $default)
            ->helperText('Number of times to retry on failure');
    }

    /**
     * Create an interval field (in minutes)
     */
    public static function interval(string $name = 'interval', int $default = 60): TextInput
    {
        return TextInput::make($name)
            ->label('Interval (minutes)')
            ->numeric()
            ->default($default)
            ->minValue(1)
            ->maxValue(10080) // 1 week
            ->placeholder((string) $default)
            ->helperText('Interval between executions in minutes');
    }

    /**
     * Create a priority field
     */
    public static function prioritySelect(string $name = 'priority'): Select
    {
        return StatusSelect::priority($name);
    }

    /**
     * Create a tag field
     */
    public static function tags(string $name = 'tags'): TextInput
    {
        return TextInput::make($name)
            ->label('Tags')
            ->maxLength(500)
            ->placeholder('tag1, tag2, tag3')
            ->helperText('Comma-separated list of tags')
            ->columnSpanFull();
    }

    /**
     * Create a version field
     */
    public static function version(string $name = 'version'): TextInput
    {
        return TextInput::make($name)
            ->label('Version')
            ->maxLength(50)
            ->placeholder('1.0.0')
            ->helperText('Version number or identifier');
    }

    /**
     * Create a size field (in bytes, MB, GB, etc.)
     */
    public static function size(string $name = 'size'): TextInput
    {
        return TextInput::make($name)
            ->label('Size')
            ->maxLength(50)
            ->placeholder('100MB, 2GB, 500KB')
            ->helperText('Size with unit (KB, MB, GB, TB)');
    }

    /**
     * Create a weight field (for load balancing, priority, etc.)
     */
    public static function weight(string $name = 'weight', int $default = 100): TextInput
    {
        return TextInput::make($name)
            ->label('Weight')
            ->numeric()
            ->default($default)
            ->minValue(0)
            ->maxValue(1000)
            ->placeholder((string) $default)
            ->helperText('Relative weight for load balancing or priority');
    }

    /**
     * Create a TTL (Time To Live) field
     */
    public static function ttl(string $name = 'ttl', int $default = 3600): TextInput
    {
        return TextInput::make($name)
            ->label('TTL (seconds)')
            ->numeric()
            ->default($default)
            ->minValue(60)
            ->maxValue(604800) // 1 week
            ->placeholder((string) $default)
            ->helperText('Time to live in seconds');
    }

    /**
     * Create a slug field with auto-generation
     */
    public static function slug(string $name = 'slug', ?string $sourceField = 'name'): TextInput
    {
        $field = TextInput::make($name)
            ->label('Slug')
            ->maxLength(255)
            ->placeholder('auto-generated-slug')
            ->helperText('URL-friendly identifier (auto-generated from name)');

        if ($sourceField) {
            $field->live(onBlur: true)
                ->afterStateUpdated(function (string $state, callable $set, ?string $old, callable $get) use ($sourceField) {
                    if (($get($sourceField) ?? '') !== '' && $state === '') {
                        $set('slug', \Illuminate\Support\Str::slug($get($sourceField)));
                    }
                });
        }

        return $field;
    }
}
