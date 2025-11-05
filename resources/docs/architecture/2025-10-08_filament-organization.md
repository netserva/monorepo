# Filament 4.1 Organization for NetServa 3.0

**Directory structure, conventions, and patterns for Filament admin panels**

---

## Overview

NetServa uses Filament 4.1 as the admin panel framework for managing infrastructure. This document outlines organization patterns specific to Filament v4 (not v3).

**Key Changes in Filament 4:**
- Schemas moved to `Schemas/Components/` (not `Forms/Components/`)
- All actions extend `Filament\Actions\Action` (no `Filament\Tables\Actions`)
- File visibility private by default
- Filters deferred by default (must click button to apply)
- Grid/Section/Fieldset no longer span all columns automatically

---

## Directory Structure

### Core Filament Organization

```
app/Filament/
├── Resources/                      # Resource classes for models
│   ├── FleetVHostResource.php      # Main resource file
│   │   ├── Pages/                  # Resource pages (Create, Edit, List)
│   │   │   ├── CreateFleetVHost.php
│   │   │   ├── EditFleetVHost.php
│   │   │   └── ListFleetVHosts.php
│   │   ├── Schemas/                # Filament v4: Form/table schemas
│   │   │   ├── FleetVHostFormSchema.php
│   │   │   └── FleetVHostTableSchema.php
│   │   ├── Actions/                # Resource-specific actions
│   │   │   ├── ProvisionVHostAction.php
│   │   │   └── FixPermissionsAction.php
│   │   └── RelationManagers/      # Manage relationships
│   │       └── VConfsRelationManager.php
│   └── FleetVNodeResource.php
│
├── Pages/                          # Custom standalone pages
│   ├── Dashboard.php               # Main dashboard
│   └── Settings/
│       └── GeneralSettings.php
│
├── Widgets/                        # Dashboard widgets
│   ├── VHostStatsWidget.php
│   └── RecentActivityWidget.php
│
└── Schemas/                        # Shared schema components (Filament v4)
    └── Components/
        └── VConfKeyValueField.php
```

### Plugin-Specific Filament Resources

```
packages/netserva-{plugin}/src/Filament/
├── Resources/
│   └── PluginResource.php
├── Pages/
│   └── PluginDashboard.php
└── Widgets/
    └── PluginStatusWidget.php
```

**Auto-discovery:** Resources in `packages/*/src/Filament/Resources/` are automatically discovered by `AdminPanelProvider`

---

## Resource Structure

### Basic Resource Template

**Location:** `app/Filament/Resources/FleetVHostResource.php`

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetVHostResource\Pages;
use App\Filament\Resources\FleetVHostResource\Schemas\FleetVHostFormSchema;
use App\Filament\Resources\FleetVHostResource\Schemas\FleetVHostTableSchema;
use App\Models\FleetVHost;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

/**
 * FleetVHost Resource
 *
 * Manages virtual hosts (domains) across vnodes (servers)
 *
 * Features:
 * - CRUD for vhosts
 * - VConf management (54+ environment variables)
 * - Provisioning actions (configure nginx, SSL)
 * - Status tracking (pending, provisioning, active, etc.)
 *
 * Authorization: FleetVHostPolicy
 * Tests: tests/Feature/Filament/FleetVHostResourceTest.php
 */
class FleetVHostResource extends Resource
{
    protected static ?string $model = FleetVHost::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 2;

    /**
     * Define form schema for create/edit pages
     *
     * Filament v4: Returns Schema, not Form
     */
    public static function schema(Schema $schema): Schema
    {
        return FleetVHostFormSchema::make($schema);
    }

    /**
     * Define table schema for list page
     *
     * Filament v4: Columns and filters configuration
     */
    public static function table(Table $table): Table
    {
        return FleetVHostTableSchema::make($table);
    }

    /**
     * Relation managers for this resource
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\VConfsRelationManager::class,
            RelationManagers\VServsRelationManager::class,
        ];
    }

    /**
     * Pages for this resource
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetVHosts::route('/'),
            'create' => Pages\CreateFleetVHost::route('/create'),
            'edit' => Pages\EditFleetVHost::route('/{record}/edit'),
        ];
    }
}
```

### Form Schema (Filament v4 Pattern)

**Location:** `app/Filament/Resources/FleetVHostResource/Schemas/FleetVHostFormSchema.php`

```php
<?php

namespace App\Filament\Resources\FleetVHostResource\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Select;

/**
 * FleetVHost Form Schema
 *
 * Filament v4 pattern: Separate schema class for maintainability
 */
class FleetVHostFormSchema
{
    public static function make(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('fleet_vnode_id')
                                    ->label('VNode (Server)')
                                    ->relationship('vnode', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Server where this vhost will be provisioned'),

                                TextInput::make('domain')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->regex('/^[a-z0-9\-\.]+$/')
                                    ->helperText('Lowercase letters, numbers, dots, hyphens only'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('php_version')
                                    ->label('PHP Version')
                                    ->options([
                                        '8.1' => 'PHP 8.1',
                                        '8.2' => 'PHP 8.2',
                                        '8.3' => 'PHP 8.3',
                                        '8.4' => 'PHP 8.4 (Latest)',
                                    ])
                                    ->default('8.4')
                                    ->required(),

                                TextInput::make('web_root')
                                    ->label('Web Root Path')
                                    ->default(fn($get) => "/srv/{$get('domain')}/web")
                                    ->required()
                                    ->maxLength(500),
                            ]),
                    ]),

                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'provisioning' => 'Provisioning',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->disabled(fn($record) => $record === null)
                            ->helperText('Status managed automatically during provisioning'),
                    ])
                    ->collapsible(),
            ]);
    }
}
```

### Table Schema (Filament v4 Pattern)

**Location:** `app/Filament/Resources/FleetVHostResource/Schemas/FleetVHostTableSchema.php`

```php
<?php

namespace App\Filament\Resources\FleetVHostResource\Schemas;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Actions\Action;

class FleetVHostTableSchema
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vnode.name')
                    ->label('VNode')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'pending',
                        'warning' => 'provisioning',
                        'success' => 'active',
                        'danger' => ['suspended', 'failed'],
                    ]),

                TextColumn::make('php_version')
                    ->label('PHP')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'failed' => 'Failed',
                    ])
                    ->deferFilters(false),  // Filament v4: Apply immediately

                SelectFilter::make('fleet_vnode_id')
                    ->label('VNode')
                    ->relationship('vnode', 'name'),
            ])
            ->actions([
                EditAction::make(),

                // Custom action: Provision vhost
                Action::make('provision')
                    ->icon('heroicon-o-cog')
                    ->requiresConfirmation()
                    ->action(fn(FleetVHost $record) =>
                        app(VHostProvisioningService::class)->provision($record)
                    )
                    ->visible(fn(FleetVHost $record) => $record->status === 'pending'),

                DeleteAction::make(),
            ])
            ->bulkActions([
                // Bulk actions here
            ]);
    }
}
```

---

## Resource Pages

### Create Page

**Location:** `app/Filament/Resources/FleetVHostResource/Pages/CreateFleetVHost.php`

```php
<?php

namespace App\Filament\Resources\FleetVHostResource\Pages;

use App\Filament\Resources\FleetVHostResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateFleetVHost extends CreateRecord
{
    protected static string $resource = FleetVHostResource::class;

    /**
     * Customize create logic
     */
    protected function afterCreate(): void
    {
        // Initialize default vconfs after creating vhost
        $vhost = $this->record;

        app(InitializeVConfsAction::class)->execute($vhost);

        Notification::make()
            ->success()
            ->title('VHost created')
            ->body("VHost {$vhost->domain} created successfully with default configuration.")
            ->send();
    }

    /**
     * Redirect after create
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
```

### Edit Page with Custom Actions

**Location:** `app/Filament/Resources/FleetVHostResource/Pages/EditFleetVHost.php`

```php
<?php

namespace App\Filament\Resources\FleetVHostResource\Pages;

use App\Filament\Resources\FleetVHostResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use App\Services\VHostProvisioningService;

class EditFleetVHost extends EditRecord
{
    protected static string $resource = FleetVHostResource::class;

    /**
     * Header actions (top right of page)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('provision')
                ->label('Provision VHost')
                ->icon('heroicon-o-cog')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will configure nginx, PHP-FPM, and request SSL certificate.')
                ->action(function () {
                    $result = app(VHostProvisioningService::class)->provision($this->record);

                    if ($result->success) {
                        Notification::make()
                            ->success()
                            ->title('VHost provisioned')
                            ->body($result->message)
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Provisioning failed')
                            ->body($result->message)
                            ->send();
                    }
                })
                ->visible(fn() => $this->record->status === 'pending'),

            Action::make('fix_permissions')
                ->label('Fix Permissions')
                ->icon('heroicon-o-lock-closed')
                ->action(fn() => app(VHostManagementService::class)->fixPermissions($this->record))
                ->visible(fn() => $this->record->status === 'active'),
        ];
    }
}
```

---

## Relation Managers

**Location:** `app/Filament/Resources/FleetVHostResource/RelationManagers/VConfsRelationManager.php`

```php
<?php

namespace App\Filament\Resources\FleetVHostResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\Toggle;

/**
 * VConf Relation Manager
 *
 * Manages 54+ environment variables for vhost
 */
class VConfsRelationManager extends RelationManager
{
    protected static string $relationship = 'vconfs';

    protected static ?string $title = 'Configuration Variables';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->length(5)  // BR-003: Exactly 5 characters
                    ->uppercase()
                    ->helperText('Exactly 5 uppercase characters (e.g., WPATH, DPASS)'),

                TextInput::make('value')
                    ->required()
                    ->maxLength(1000),

                Select::make('category')
                    ->options([
                        'paths' => 'Paths',
                        'credentials' => 'Credentials',
                        'settings' => 'Settings',
                        'ssl' => 'SSL',
                        'mail' => 'Mail',
                        'dns' => 'DNS',
                    ])
                    ->required(),

                Toggle::make('is_sensitive')
                    ->label('Sensitive (mask in display)')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value')
                    ->limit(50)
                    ->formatStateUsing(fn($record) =>
                        $record->is_sensitive ? '••••••••' : $record->value
                    ),

                TextColumn::make('category')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'credentials' => 'danger',
                        'ssl' => 'success',
                        default => 'secondary',
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }
}
```

---

## Filament v4 Best Practices

### Use Schemas Directory (v4 Change)

```php
// ✅ Filament v4: Schemas in dedicated directory
app/Filament/Resources/FleetVHostResource/Schemas/
    FleetVHostFormSchema.php
    FleetVHostTableSchema.php

// ❌ Old v3 pattern
app/Filament/Resources/FleetVHostResource/Forms/
    FleetVHostForm.php
```

### Actions Namespace (v4 Change)

```php
// ✅ Filament v4: All actions extend Filament\Actions\Action
use Filament\Actions\Action;

Action::make('provision')
    ->action(fn() => /* ... */);

// ❌ Old v3: Separate table actions
use Filament\Tables\Actions\Action;  // No longer exists in v4
```

### Defer Filters Explicitly (v4 Default Changed)

```php
// Filament v4: Filters deferred by default (must click "Apply")
// To apply immediately:
SelectFilter::make('status')
    ->deferFilters(false);  // Apply on change
```

### File Visibility (v4 Default Changed)

```php
// Filament v4: Files private by default
FileUpload::make('attachment')
    ->visibility('private');  // Default in v4

// Make public explicitly if needed
FileUpload::make('avatar')
    ->visibility('public');
```

---

## Testing Filament Resources

### Feature Test Template

**Location:** `tests/Feature/Filament/FleetVHostResourceTest.php`

```php
<?php

use App\Filament\Resources\FleetVHostResource;
use App\Filament\Resources\FleetVHostResource\Pages\ListFleetVHosts;
use App\Filament\Resources\FleetVHostResource\Pages\CreateFleetVHost;
use App\Models\FleetVHost;
use App\Models\FleetVNode;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('can list vhosts', function () {
    $vhosts = FleetVHost::factory()->count(3)->create();

    livewire(ListFleetVHosts::class)
        ->assertCanSeeTableRecords($vhosts);
});

test('can create vhost', function () {
    $vnode = FleetVNode::factory()->create();

    livewire(CreateFleetVHost::class)
        ->fillForm([
            'fleet_vnode_id' => $vnode->id,
            'domain' => 'test.example.com',
            'php_version' => '8.4',
            'web_root' => '/srv/test.example.com/web',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(FleetVHost::class, [
        'domain' => 'test.example.com',
        'fleet_vnode_id' => $vnode->id,
    ]);
});

test('can call provision action', function () {
    $vhost = FleetVHost::factory()->create(['status' => 'pending']);

    livewire(EditFleetVHost::class, ['record' => $vhost->id])
        ->callAction('provision')
        ->assertNotified();

    expect($vhost->fresh()->status)->toBe('provisioning');
});
```

---

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
