# NetServa Admin

Filament admin panel providing CRUD interfaces for NetServa Core features:
- Settings management (key:value store)
- Plugin management (enable/disable, configure)
- Audit log viewer

## Installation

```bash
composer require netserva/admin
```

This will automatically pull in `netserva/core` as a dependency.

## Usage

Register the AdminPlugin in your Filament panel provider:

```php
use NetServa\Admin\AdminPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(AdminPlugin::make());
}
```

## Features

### Settings Management
- Create/edit/delete settings via Filament UI
- Organize settings by category (mail.*, dns.*, web.*)
- Support for string, integer, boolean, and JSON values
- Search and filter capabilities

### Plugin Management
- View all registered plugins
- Enable/disable plugins with dependency checking
- View plugin metadata (version, description, dependencies)
- Configure plugin-specific settings

### Audit Logs
- View complete audit trail
- Filter by date, user, action
- Search logs
- Read-only interface

## Requirements

- PHP ^8.4
- Laravel ^12.0
- Filament ^4.0
- netserva/core ^0.0.1
